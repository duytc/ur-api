<?php

namespace UR\Worker\Job\Linear;

use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

class RemoveAllDataFromDataSet implements JobInterface
{
    const JOB_NAME = 'removeAllDataFromDataSet';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger, DataSetManagerInterface $dataSetManager)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // do create all linear jobs for each files
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->error('data set with ID (%s) not found when Remove All Data From Data Set', $dataSetId);
            return;
        }

        $this->scheduler->addJob([
            'task' => TruncateDataSetSubJob::JOB_NAME,
        ], $dataSetId, $params);

        $this->scheduler->addJob([
            'task' => UpdateDataSetTotalRowSubJob::JOB_NAME
        ], $dataSetId, $params);

        $this->scheduler->addJob([
            'task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME
        ], $dataSetId, $params);
    }
}