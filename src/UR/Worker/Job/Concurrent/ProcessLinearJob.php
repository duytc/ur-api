<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\Common\Persistence\ObjectManager;
use Exception;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobExpirerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\JobWorkerPool;
use Redis;

class ProcessLinearJob implements LockableJobInterface
{
    const JOB_NAME = 'processLinearJob';

    const WORKER_EXIT_CODE_SUCCESS = 0;
    const WORKER_EXIT_CODE_REQUEST_STOP_SUCCESS = 99;
    const WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB = 98;
    const MAX_JOB_COUNT_FOR_A_SESSION = 10;

    /**
     * @var JobWorkerPool
     */
    private $linearWorkerPool;

    /**
     * @var JobCounterInterface
     */
    private $jobCounter;

    /**
     * @var
     */
    private $jobExpirer;

    /**
     * @var ObjectManager
     */
    private $entityManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var  Redis */
    private $redis;

    private $releaseJobOnLockedDelaySeconds;

    public function __construct(JobWorkerPool $linearWorkerPool, JobCounterInterface $jobCounter, JobExpirerInterface $jobExpirer, ObjectManager $entityManager, LoggerInterface $logger, Redis $redis, $releaseJobOnLockedDelaySeconds)
    {
        $this->linearWorkerPool = $linearWorkerPool;
        $this->jobCounter = $jobCounter;
        $this->jobExpirer = $jobExpirer;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->redis = $redis;
        $this->releaseJobOnLockedDelaySeconds = $releaseJobOnLockedDelaySeconds;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function getLockKeys(JobParams $params): array
    {
        $lockKeys = [];

        // lock processLinear together to make sure one worker for a linear tube
        // lock by data set
        $lockKey = $params->getRequiredParam('linear_tube');

        // lock processLinear with load file to support loading files concurrently
        $uniqueId = $params->getParam(LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_UNIQUE_ID, null);
        if (!empty($uniqueId)) {
            // lock by connected data source
            $lockKey = sprintf('%s-%s', $lockKey, $uniqueId);
        }

        $lockKeys[] = $lockKey;

        return $lockKeys;
    }

    public function run(JobParams $params)
    {
        $concurrentRedisKey = $params->getParam(LoadFilesConcurrentlyIntoDataSet::CONCURRENT_LOADING_FILE_COUNT_REDIS_KEY);
        if (!empty($concurrentRedisKey) && $this->jobCounter->getPendingJobCount($concurrentRedisKey) < 1) {
            //Finish loading files for current connected data source
            return self::WORKER_EXIT_CODE_SUCCESS;
        }

        $linearTubeName = $params->getRequiredParam('linear_tube');
        $beanstalkHost = $params->getRequiredParam('beanstalk_host');

        $foundLinearJob = false;

        // use a separate beanstalk instance so no conflict with concurrent instance
        $beanstalk = new Pheanstalk($beanstalkHost);

        $exitCode = self::WORKER_EXIT_CODE_SUCCESS;
        $jobCount = 0;
        while (1) {
            $jobCount++;
            if ($jobCount > self::MAX_JOB_COUNT_FOR_A_SESSION) {
                // Free worker to try process other linear jobs.
                // Current linear job will be released and tried in other workers
                $exitCode = ProcessLinearJob::WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB;
                break;
            }

            $exitCode = self::WORKER_EXIT_CODE_SUCCESS; // reset
            $this->logger->debug(sprintf('[ProcessLinear] Waiting for new linear job'));

            $linearJob = $beanstalk
                ->watchOnly($linearTubeName)
                ->ignore('default')
                ->reserve(0); // do not block on reserve, do not specify a timeout, this allows us to end immediately if there is no jobs to process

            if (!$linearJob instanceof Job) {
                //Now none job in `ready` state
                //But `delay` state have 100+ jobs. We need move those jobs from `delay` to `ready`.
                try {
                    $beanstalk->kick(100);
                } catch (Exception $e) {

                }
                $foundLinearJob = false;

                break;
            }

            $foundLinearJob = true;

            try {
                $linearJobParams = new JobParams(json_decode($linearJob->getData(), true));

                $task = $linearJobParams->getRequiredParam('task');
                $timestamp = (int)$linearJobParams->getRequiredParam('timestamp');
                $priority = $linearJobParams->getRequiredParam('priority');

                $this->logger->notice(sprintf('Received Linear job %s (ID: %s) with params %s', $task, $linearJob->getId(), $linearJob->getData()));

                // find job worker
                $jobWorker = $this->linearWorkerPool->findJob($task);
                if (!$jobWorker) {
                    $this->logger->error(sprintf('[ProcessLinear] The task "%s" is unknown', $task));
                    $beanstalk->bury($linearJob);
                    continue;
                }

                // check if job expired
                if ($jobWorker instanceof ExpirableJobInterface && $this->jobExpirer->isExpired($linearTubeName, $timestamp)) {
                    $this->logger->notice(sprintf('[ProcessLinear] Linear Job (ID: %s) has expired, moving on. Params: %s', $linearJob->getId(), $linearJob->getData()));
                    $beanstalk->delete($linearJob);
                    continue;
                }

                // run job worker
                $exitCode = $jobWorker->run($linearJobParams);

                $this->logger->notice(
                    sprintf(
                        '[ProcessLinear] Linear job (ID: %s) "%s" with params %s has been completed with exitCode %s',
                        $linearJob->getId(),
                        $task,
                        $linearJob->getData(),
                        $exitCode
                    )
                );

                // check if exitCode is waiting for concurrent loading files
                // if true, we should keep job in data set linear tube for next execution
                if ($exitCode === self::WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB) {
                    $this->logger->notice(sprintf('[ProcessLinear] Linear job (ID: %s) with params %s return exitCode %s, then will be retried later', $linearJob->getId(), $linearJob->getData(), $exitCode));

                    // we stop execution here and rely on the system restarting when ready
                    break;
                }

                // delete linear job when it finished
                $this->logger->notice(sprintf('[ProcessLinear] Deleting linear job (ID: %s) with params %s', $linearJob->getId(), $linearJob->getData()));
                $beanstalk->delete($linearJob);
            } catch (\Exception $e) {
                $this->logger->warning(sprintf('[ProcessLinear] Linear job (ID: %s) with params %s failed', $linearJob->getId(), $linearJob->getData()));
                $this->logger->warning($e);
                $beanstalk->bury($linearJob);
            } finally {
                // this should get executed even there is exception or a continue statement above

                $this->entityManager->clear();
                gc_collect_cycles();
            }
        }

        $beanstalk->getConnection()->disconnect();
        $beanstalk = null;

        if (!$foundLinearJob) {
            $this->logger->notice(sprintf('[ProcessLinear] No linear jobs in "%s". They may have already been processed', $linearTubeName));
        }

        return $exitCode;
    }
}