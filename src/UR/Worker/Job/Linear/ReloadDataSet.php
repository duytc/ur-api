<?php

namespace UR\Worker\Job\Linear;

use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Repository\Core\ConnectedDataSourceRepositoryInterface;

class ReloadDataSet implements SplittableJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'reloadDataSet';

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
    private $dataSetManager;

    /**
     * @var ConnectedDataSourceRepositoryInterface
     */
    private $connectedDataSourceRepository;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger, DataSetManagerInterface $dataSetManager, ConnectedDataSourceRepositoryInterface $connectedDataSourceRepository)
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->connectedDataSourceRepository = $connectedDataSourceRepository;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return static::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);

        if (!is_integer($dataSetId)) {
            return;
        }

        // remove data first
        $this->scheduler->addJob([
            'task' => TruncateDataSetSubJob::JOB_NAME
        ], $dataSetId, $params);

        //get data set by Id
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->dataSetManager->find($dataSetId);

        /** @var ConnectedDataSourceInterface[] $connectedDataSources */
        $connectedDataSources = $this->connectedDataSourceRepository->getConnectedDataSourceByDataSet($dataSet);
        if ($connectedDataSources instanceof Collection) {
            $connectedDataSources = $connectedDataSources->toArray();
        }

        usort($connectedDataSources, function (ConnectedDataSourceInterface $a, ConnectedDataSourceInterface $b) {
            if ($a->getId() == $b->getId()) {
                return 0;
            }
            return ($a->getId() < $b->getId()) ? -1 : 1;
        });

        $jobs = [];
        foreach ($connectedDataSources as $connectedDataSource) {
            $entryIds = [];
            // get all entries by connected data source
            $entries = $connectedDataSource->getDataSource()->getDataSourceEntries();
            $entryIds = array_merge($entryIds, array_map(function (DataSourceEntryInterface $entry) {
                return $entry->getId();

            }, $entries->toArray()));

            foreach ($entryIds as $entryId) {
                $jobs[] = [
                    'task' => LoadFileIntoDataSetSubJob::JOB_NAME,
                    LoadFileIntoDataSetSubJob::ENTRY_ID => $entryId,
                    LoadFileIntoDataSetSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
                ];
            }
        }

        if (count($jobs) > 0) {
            $this->scheduler->addJob($jobs, $dataSetId, $params);

            // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
            // this will save a lot of execution time
            $this->scheduler->addJob([
                'task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME
            ], $dataSetId, $params);

            $this->scheduler->addJob([
                'task' => UpdateDataSetTotalRowSubJob::JOB_NAME
            ], $dataSetId, $params);

            $this->scheduler->addJob([
                'task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME
            ], $dataSetId, $params);
        }
    }
}