<?php

// needed for handling signals
declare(ticks = 1);

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Monolog\Logger;

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

$pid = getmypid();
$requestStop = false;

$appShutdown = function () use (&$requestStop, $pid, &$logger) {
    $logger->notice(sprintf("Worker PID %d has received a request to stop gracefully", $pid));
    $requestStop = true; // set reference value to true to stop worker loop after current job
};

// when TERM signal is sent to this process, we gracefully shutdown after current job is finished processing
// when KILL signal is sent (i.e ctrl-c) we stop immediately
// You can test this by calling "kill -TERM PID" where PID is the PID of this process, the process will end after the current job
pcntl_signal(SIGINT, $appShutdown);
pcntl_signal(SIGTERM, $appShutdown);

const RESERVE_TIMEOUT = 5; // seconds
const RELEASE_JOB_DELAY_SECONDS = 5;
const JOB_LOCK_TTL = 3 * (60 * 60 * 1000); // 3 hour expiry time for lock

$entityManager = $container->get('doctrine.orm.entity_manager');

/** @var Logger $logger */
$logger = $container->get('logger');
$logHandler = new \Monolog\Handler\StreamHandler("php://stderr", Logger::DEBUG);
$logHandler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, false, true));
$logHandler->pushProcessor(new \Monolog\Processor\TagProcessor([
    'PID' => $pid
]));
$logger->pushHandler($logHandler);


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

    // prevent duplicate logs being printed while waiting
    if ($newJobArrived) {
        $logger->debug('Waiting for job to process');
    }

    $newJobArrived = false;

    $job = $beanstalk->watch($concurrentTubeName)
        ->ignore('default')
        ->reserve(RESERVE_TIMEOUT);

    if (!$job) {
        continue;
    }

    $newJobArrived = true;

    $rawJobData = $job->getData();

    $jobLock = false;

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

        if ($jobWorker instanceof \Pubvantage\Worker\Job\LockableJobInterface) {
            $jobLock = $redLock->lock($lockKeyPrefix . $jobWorker->getLockKey($jobParams), JOB_LOCK_TTL, [
                'pid' => $pid
            ]);

            if ($jobLock === false) {
                $logger->debug(sprintf('Cannot acquire job lock. Job %s (ID: %s) will be retried later', $job->getId(), $rawJobData));
                $beanstalk->release($job, PheanstalkProxy::DEFAULT_PRIORITY, RELEASE_JOB_DELAY_SECONDS);
                continue;
            }
        }

        $jobWorker->run($jobParams);
        $beanstalk->delete($job);

        $logger->notice(sprintf('Job %s (ID: %s) with params %s has been completed', $task, $job->getId(), $rawJobData));
    } catch (Exception $e) {
        $logger->warning(sprintf('Job (ID: %s) with params %s failed', $job->getId(), $rawJobData));
        $logger->warning($e);
        $beanstalk->bury($job);
    } finally {
        // always release lock if it is set
        if (is_array($jobLock)) {
            $redLock->unlock($jobLock);
        }

        $entityManager->clear();
        gc_collect_cycles();
    }
}
