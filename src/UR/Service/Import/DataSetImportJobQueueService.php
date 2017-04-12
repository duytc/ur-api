<?php

namespace UR\Service\Import;


use DateTime;
use Monolog\Logger;
use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\Model\Core\DataSetImportJobInterface;

class DataSetImportJobQueueService
{
    /**
     * @var DataSetImportJobManagerInterface
     */
    private $dataSetImportJobManager;

    function __construct(DataSetImportJobManagerInterface $dataSetImportJobManager)
    {
        $this->dataSetImportJobManager = $dataSetImportJobManager;
    }

    /**
     * @param $dataSetId
     * @param $importJobId
     * @param Logger $logger
     * @return bool|DataSetImportJobInterface
     */
    public function isExecuteJob($dataSetId, $importJobId, Logger $logger)
    {
        /**@var DataSetImportJobInterface $executeJob */
        try {
            $executeJob = $this->dataSetImportJobManager->getExecuteImportJobByDataSetId($dataSetId);
        } catch (\Exception $e) {
            $logger->notice(sprintf('DataSet with id %d does not have an import job with id %s, putting job back into the queue', $dataSetId, $importJobId));
            // temporary fix for race condition that causes sync problems
            // check that the API creates this database entry BEFORE the beanstalk job
            return false;
        }

        // more validation to prevent any chance of error
        if (!$executeJob instanceof DataSetImportJobInterface) {
            return false;
        }

        /*
         * check if data set has another job before this job, put job back to queue
         * this make sure jobs are executes in order
         * job is created and not to be executed in 1 hour will be delete
         */
        if ($executeJob->getJobId() !== $importJobId) {
            $logger->notice(sprintf('DataSet with id %d is busy, putting job %s back into the queue', $dataSetId, $importJobId));
            $dateTime = new Datetime('now');
            $subtractHour = ($dateTime->getTimestamp() - $executeJob->getCreatedDate()->getTimestamp()) / 3600;
            if ($subtractHour > 1) {
                $this->dataSetImportJobManager->delete($executeJob);
            }

            return false;
        }

        return $executeJob;
    }

    public function deleteJob(DataSetImportJobInterface $exeCuteJob)
    {
        $this->dataSetImportJobManager->delete($exeCuteJob);
    }

}