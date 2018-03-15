<?php

// needed for handling signals
declare(ticks = 1);

const WORKER_EXIT_CODE_REQUEST_STOP_SUCCESS = 99;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Monolog\Logger;
use UR\Worker\Job\Concurrent\LoadFilesConcurrentlyIntoDataSet;
use UR\Worker\Job\Concurrent\ProcessLinearJob;

$loader = require_once __DIR__ . '/../app/autoload.php';

require_once __DIR__ . '/../app/AppKernel.php';

$env = getenv('SYMFONY_ENV') ?: 'prod';
$debug = false;

if ($env == 'dev') {
    $debug = true;
}

$kernel = new AppKernel($env, $debug);
$kernel->boot();

/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer();

$concurrentTubeName = $container->getParameter('ur.worker.concurrent_tube_name');
$lockKeyPrefix = $container->getParameter('ur.worker.lock_key_prefix');
$releaseJobOnLockedDelaySeconds = $container->getParameter('ur.worker.lock.delay_time_on_release');
$releaseJobOnLockedDelaySeconds = filter_var($releaseJobOnLockedDelaySeconds, FILTER_VALIDATE_INT); // to int
$releaseJobOnLockedDelaySeconds = $releaseJobOnLockedDelaySeconds === false || $releaseJobOnLockedDelaySeconds < 0 ? false : $releaseJobOnLockedDelaySeconds; // make sure positive int

$pid = getmypid();
$requestStop = false;

$appShutdown = function () use (&$requestStop, $pid, &$logger) {
    $logger->notice(sprintf("Worker PID %d has received a request to stop gracefully", $pid));
    $requestStop = true; // set reference value to true to stop worker loop after current job
};

// You can test this by calling "kill -USR1 PID" where PID is the PID of this process, the process will end after the current job
pcntl_signal(SIGUSR1, $appShutdown);

const RESERVE_TIMEOUT = 1; // seconds
const RELEASE_JOB_DELAY_SECONDS = 5;
const JOB_LOCK_TTL = 3 * (60 * 60 * 1000); // 3 hour expiry time for lock
const WORKER_TIME_LIMIT = 10800; // 3 hours
// Set the start time
$startTime = time();
$entityManager = $container->get('doctrine.orm.entity_manager');

/** @var Logger $logger */
$logger = $container->get('logger');
$logHandler = new \Monolog\Handler\StreamHandler("php://stderr", Logger::DEBUG);
$logHandler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, false, true));
$logHandler->pushProcessor(new \Monolog\Processor\TagProcessor([
    'PID' => $pid
]));
$logger->pushHandler($logHandler);

/** @var PheanstalkProxy $beanstalk */
$beanstalk = $container->get('leezy.pheanstalk');

$redis = $container->get('ur.redis.app_cache');

$redLock = new Pubvantage\RedLock([$redis]);

$concurrentJobScheduler = $container->get('ur.worker.scheduler.concurrent_job_scheduler');
$linearJobScheduler = $container->get('ur.worker.scheduler.linear_job_scheduler');
$dataSetJobScheduler = $container->get('ur.worker.scheduler.data_set_job_scheduler');

// this worker pool is not watched by this main worker process
$linearWorkerPool = $container->get('ur.worker.job.linear.worker_pool');

$concurrentWorkerPool = $container->get('ur.worker.job.concurrent.worker_pool');

$logger->notice(sprintf("Worker PID %d has started", $pid));

$newJobArrived = true;
while (1) {
    if ($requestStop) {
        // exit worker gracefully, supervisord will restart it
        $logger->notice(sprintf("Worker PID %d is stopping by user request", $pid));
        break;
    }

    if (time() > ($startTime + WORKER_TIME_LIMIT)) {
        // exit worker gracefully, supervisord will restart it
        $logger->notice(sprintf("Worker PID %d is stopping because time limit has been exceeded", $pid));
        break;
    }

    // prevent duplicate logs being printed while waiting
    if ($newJobArrived) {
        $logger->debug('Waiting for job to process');
    }

    $newJobArrived = false;

    //If none job in `ready` state, it's take 5 seconds to wait (RESERVE_TIMEOUT).
    //We need reduce timeout to 1 second
    $job = $beanstalk->watch($concurrentTubeName)
        ->ignore('default')
        ->reserve(RESERVE_TIMEOUT);

    if (!$job instanceof \Pheanstalk\Job) {
        //Now none job in `ready` state
        //But `delay` state have 100+ jobs. We need move those jobs from `delay` to `ready`.
        try {
            $beanstalk->kick(100);
        } catch (Exception $e) {
        
        }

        continue;
    }

    $newJobArrived = true;

    $rawJobData = $job->getData();

    $task = null;
    $jobWorker = false;
    $jobLocks = [];
    $exitCode = LoadFilesConcurrentlyIntoDataSet::LOAD_FILE_CONCURRENT_EXIT_CODE_FAILED;
    try {
        $jobParams = new \Pubvantage\Worker\JobParams(json_decode($rawJobData, true));
        $task = $jobParams->getRequiredParam('task');

        $logger->notice(sprintf('Received job %s (ID: %s) with params %s', $task, $job->getId(), $rawJobData));

        $jobWorker = $concurrentWorkerPool->findJob($task);

        if (!$jobWorker) {
            $logger->error(sprintf('The task "%s" is unknown', $task));
            $beanstalk->bury($job);
            continue;
        }

        if ($jobWorker instanceof \Pubvantage\Worker\Job\LockableJobInterface && count($jobWorker->getLockKeys($jobParams)) > 0) {
            $currentLockKey = '';
            foreach ($jobWorker->getLockKeys($jobParams) as $lockKey) {
                $jobLock = $redLock->lock($lockKeyPrefix . $lockKey, JOB_LOCK_TTL, [
                    'pid' => $pid
                ]);

                if ($jobLock === false) {
                    $currentLockKey = $lockKeyPrefix . $lockKey;
                    break;
                }

                $jobLocks[] = $jobLock;
            }

            if (empty($jobLocks)) {
                $logger->debug(sprintf('Cannot acquire job lock [lockKey=%s]. Job %s (ID: %s) with params %s will be retried later', $currentLockKey, $task, $job->getId(), $rawJobData));
                $beanstalk->release($job, PheanstalkProxy::DEFAULT_PRIORITY, $releaseJobOnLockedDelaySeconds ? $releaseJobOnLockedDelaySeconds : RELEASE_JOB_DELAY_SECONDS);
                continue;
            }
        }

        $exitCode = $jobWorker->run($jobParams);

        if ($jobWorker instanceof ProcessLinearJob && $exitCode === ProcessLinearJob::WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB) {
            $logger->notice(sprintf('Job %s (ID: %s) with params %s return exitCode %s, then will be retried later', $task, $job->getId(), $rawJobData, $exitCode));
            $beanstalk->release($job, PheanstalkProxy::DEFAULT_PRIORITY, $releaseJobOnLockedDelaySeconds ? $releaseJobOnLockedDelaySeconds : RELEASE_JOB_DELAY_SECONDS);
        } else {
            $beanstalk->delete($job);
        }

        $logger->notice(sprintf('Job %s (ID: %s) with params %s has been completed', $task, $job->getId(), $rawJobData));
    } catch (Exception $e) {
        $logger->warning(sprintf('Job (ID: %s) with params %s failed', $job->getId(), $rawJobData));
        $logger->warning($e);
        $beanstalk->bury($job);
    } finally {
        // always release lock if it is set
        if (is_array($jobLocks)) {
            foreach ($jobLocks as $jobLock) {
                $logger->debug(sprintf('Unlocking job lock %s for Job %s (ID: %s)', implode('-', $jobLock), $task, $job->getId()));
                $redLock->unlock($jobLock);
            }
        }

        $entityManager->clear();
        gc_collect_cycles();

        if (FALSE == $entityManager->getConnection()->ping()) {
            $entityManager->getConnection()->close();
            $entityManager->getConnection()->connect();
        }

        if (FALSE == $entityManager->getConnection()->ping()) {
            $logger->warning("MySQL server has gone away. Contact system admin and restart worker");
        }
    }
}

if ($requestStop) {
    exit(WORKER_EXIT_CODE_REQUEST_STOP_SUCCESS); // otherwise use 0 status code
}
