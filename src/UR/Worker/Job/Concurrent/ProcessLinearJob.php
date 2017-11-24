<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\Common\Persistence\ObjectManager;
use Pheanstalk\Pheanstalk;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\JobExpirerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\JobWorkerPool;

class ProcessLinearJob implements LockableJobInterface
{
    const JOB_NAME = 'processLinearJob';

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

    public function __construct(JobWorkerPool $linearWorkerPool, JobCounterInterface $jobCounter, JobExpirerInterface $jobExpirer, ObjectManager $entityManager, LoggerInterface $logger)
    {
        $this->linearWorkerPool = $linearWorkerPool;
        $this->jobCounter = $jobCounter;
        $this->jobExpirer = $jobExpirer;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function getLockKey(JobParams $params): string
    {
        return $params->getRequiredParam('linear_tube');
    }

    public function run(JobParams $params)
    {
        $linearTubeName = $params->getRequiredParam('linear_tube');
        // TODO: remove when stable
        $beanstalkHost = $params->getRequiredParam('beanstalk_host');

        $foundLinearJob = false;

        // use a separate beanstalk instance so no conflict with concurrent instance
        $beanstalk = new Pheanstalk($beanstalkHost);

        while (1) {
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

                $jobWorker->run($linearJobParams);

                $this->logger->notice(
                    sprintf(
                        'Linear job (ID: %s) "%s" with priority %d has been completed',
                        $linearJob->getId(),
                        $task,
                        $priority
                    )
                );

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
    }
}