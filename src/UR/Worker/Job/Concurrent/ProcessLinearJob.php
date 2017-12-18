<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\Common\Persistence\ObjectManager;
use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\Job\LinearWithConcurrentJobInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobExpirerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\JobWorkerPool;
use Redis;
use UR\Worker\Job\Linear\LoadFileIntoDataSetLinearWithConcurrentSubJob;
use UR\Worker\Job\Linear\LoadFilesIntoDataSet;
use UR\Worker\Job\Linear\ReloadConnectedDataSource;
use UR\Worker\Job\Linear\ReloadDataSet;

class ProcessLinearJob implements LockableJobInterface
{
    const JOB_NAME = 'processLinearJob';

    const WORKER_EXIT_CODE_SUCCESS = 0;
    const WORKER_EXIT_CODE_REQUEST_STOP_SUCCESS = 99;
    const WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB = 98;

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

    public function __construct(JobWorkerPool $linearWorkerPool, JobCounterInterface $jobCounter, JobExpirerInterface $jobExpirer, ObjectManager $entityManager, LoggerInterface $logger, Redis $redis)
    {
        $this->linearWorkerPool = $linearWorkerPool;
        $this->jobCounter = $jobCounter;
        $this->jobExpirer = $jobExpirer;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->redis = $redis;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function getLockKeys(JobParams $params): array
    {
        $lockKeys = [];

        // lock processLinear together to make sure one worker for a linear tube
        $lockKey = $params->getRequiredParam('linear_tube');
        $lockKeys[] = $lockKey;

        // lock processLinear with load file to support loading files concurrently
        $uniqueId = $params->getParam(LoadFilesConcurrentlyIntoDataSet::UNIQUE_ID, null);
        if (!empty($uniqueId)) {
            $lockKey = sprintf('%s-%s', $lockKey, $uniqueId);
            $lockKeys[] = $lockKey;
        }

        return $lockKeys;
    }

    public function run(JobParams $params)
    {
        $linearTubeName = $params->getRequiredParam('linear_tube');
        $beanstalkHost = $params->getRequiredParam('beanstalk_host');

        $foundLinearJob = false;

        // use a separate beanstalk instance so no conflict with concurrent instance
        $beanstalk = new Pheanstalk($beanstalkHost);

        $exitCode = self::WORKER_EXIT_CODE_SUCCESS;

        while (1) {
            $exitCode = self::WORKER_EXIT_CODE_SUCCESS; // reset

            $linearJob = $beanstalk
                ->watchOnly($linearTubeName)
                ->ignore('default')
                ->reserve(0); // do not block on reserve, do not specify a timeout, this allows us to end immediately if there is no jobs to process

            if (!$linearJob) {
                break;
            }

            $foundLinearJob = true;

            try {
                $linearJobParams = new JobParams(json_decode($linearJob->getData(), true));

                $task = $linearJobParams->getRequiredParam('task');
                $timestamp = $linearJobParams->getRequiredParam('timestamp');
                $priority = $linearJobParams->getRequiredParam('priority');

                $jobWorker = $this->linearWorkerPool->findJob($task);

                if (
                    !$params->checkParamExist(LoadFilesConcurrentlyIntoDataSet::UNIQUE_ID)
                    && $jobWorker instanceof LoadFileIntoDataSetLinearWithConcurrentSubJob
                ) {
                    $this->logger->warning(sprintf('Linear job (ID: %s) with params %s stopped processing because processLinearJob is not for unlocking data set line tube', $linearJob->getId(), $linearJob->getData()));
                    break;
                }

                if (!$jobWorker) {
                    $this->logger->error(sprintf('The task "%s" is unknown', $task));
                    $beanstalk->bury($linearJob);
                    continue;
                }

                if ($jobWorker instanceof ExpirableJobInterface && $this->jobExpirer->isExpired($linearTubeName, $timestamp)) {
                    $this->logger->notice(sprintf('Job has expired, moving on. Params: %s', $linearJob->getData()));
                    $beanstalk->delete($linearJob);
                    continue;
                }

                $exitCode = $jobWorker->run($linearJobParams);

                $this->logger->notice(
                    sprintf(
                        'Linear job (ID: %s) "%s" with params %s has been completed with exitCode %s',
                        $linearJob->getId(),
                        $task,
                        $linearJob->getData(),
                        $exitCode
                    )
                );

                if ($exitCode === self::WORKER_EXIT_CODE_WAIT_FOR_LINEAR_WITH_CONCURRENT_JOB) {
                    $this->logger->notice(sprintf('Linear job (ID: %s) with params %s return exitCode %s, then will be retried later', $linearJob->getId(), $linearJob->getData(), $exitCode));

                    // we stop execution here and rely on the system restarting when ready
                    break;
                }

                $beanstalk->delete($linearJob);
            } catch
            (\Exception $e) {
                $this->logger->warning(sprintf('Linear job (ID: %s) with params %s failed', $linearJob->getId(), $linearJob->getData()));
                $this->logger->warning($e);
                $beanstalk->bury($linearJob);
            } finally {
                // this should get executed even there is exception or a continue statement above
                $this->jobCounter->decrementPendingJobCount($linearTubeName);

                $this->entityManager->clear();
                gc_collect_cycles();
            }
        }

        $beanstalk->getConnection()->disconnect();
        $beanstalk = null;

        if (!$foundLinearJob) {
            $this->logger->notice(sprintf('No linear jobs  in "%s". They may have already been processed', $linearTubeName));
        }

        return $exitCode;
    }
}