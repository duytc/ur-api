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
            ['task' => TruncateDataSetSubJob::JOB_NAME],

            // also update data set total row, after each entry done, to let UI does not make user confused
            // i.e: last time, pending jobs changes from 90->60 but total rows still 0 in UI and only updated after all jobs are done
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],

            // also update connected data source total row similar above
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]
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

                // also update connected data source total row similar above
                //$jobs[] = ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME];
            }

            // update connected data source that it is reload completed
            $jobs[] = [
                'task' => UpdateConnectedDataSourceReloadCompleted::JOB_NAME,
                UpdateConnectedDataSourceReloadCompleted::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId()
            ];
        }

        if (count($jobs) > 0) {
            $jobs = array_merge($jobs, [
                ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
                // need update again because after overwriting date in data set the total rows may be change
                ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
                ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]
            ]);

            // also update connected data source total row similar above
            $jobs[] = ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME];
        }

        $jobs[] = ['task' => UpdateDataSetReloadCompleted::JOB_NAME];

        // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
        // this will save a lot of execution time
        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}