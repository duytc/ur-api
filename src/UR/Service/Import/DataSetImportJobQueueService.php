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
        /**@var DataSetImportJobInterface $exeCuteJob */
        $exeCuteJob = $this->dataSetImportJobManager->getExecuteImportJobByDataSetId($dataSetId);

        /*
         * check if data set has another job before this job, put job back to queue
         * this make sure jobs are executes in order
         * job is created and not to be executed in 1 hour will be delete
         */
        if ($exeCuteJob->getJobId() !== $importJobId) {
            $logger->notice(sprintf('DataSet with id %d is busy, putted job back into the queue', $dataSetId));
            $dateTime = new Datetime('now');
            $subtractHour = ($dateTime->getTimestamp() - $exeCuteJob->getCreatedDate()->getTimestamp()) / 3600;
            if ($subtractHour > 1) {
                $this->dataSetImportJobManager->delete($exeCuteJob);
            }

            return false;
        }

        return $exeCuteJob;
    }

    public function deleteJob(DataSetImportJobInterface $exeCuteJob)
    {
        $this->dataSetImportJobManager->delete($exeCuteJob);
    }

}