<?php

namespace UR\Worker\Job\Linear;

use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Repository\Core\ConnectedDataSourceRepositoryInterface;
use UR\Service\DataSet\DataSetTableUtilInterface;
use UR\Service\DataSet\ReloadParams;
use UR\Service\DataSet\ReloadParamsInterface;

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

	protected $dataSetTableUtil;
    protected $importHistoryManager;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger,
                                DataSetManagerInterface $dataSetManager, ConnectedDataSourceRepositoryInterface $connectedDataSourceRepository,
                                DataSetTableUtilInterface $dataSetTableUtil, ImportHistoryManagerInterface $importHistoryManager )
    {
        $this->scheduler = $scheduler;
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->connectedDataSourceRepository = $connectedDataSourceRepository;
		$this->dataSetTableUtil = $dataSetTableUtil;
        $this->importHistoryManager = $importHistoryManager;
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
        $reloadType = $params->getRequiredParam(ReloadParamsInterface::RELOAD_TYPE);
        $reloadStartDate = $params->getParam(ReloadParamsInterface::RELOAD_START_DATE);
        $reloadEndDate = $params->getParam(ReloadParamsInterface::RELOAD_END_DATE);

        $reloadParameter =  new ReloadParams($reloadType, $reloadStartDate, $reloadEndDate);

        if (!is_integer($dataSetId)) {
            return;
        }

        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }
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
            $entryIds = $this->dataSetTableUtil->getEntriesByReloadParameter($connectedDataSource, $reloadParameter);
            if (empty($entryIds)) {
                $this->logger->notice(sprintf('No entry of connected data source %d reload', $connectedDataSource->getId()));
                continue;
            }
            foreach ($entryIds as $entryId) {
                $this->importHistoryManager->deleteImportHistoryByConnectedDataSourceAndEntry($connectedDataSource->getId(), $entryId);
                $jobs[] = [
                    'task' => LoadFileIntoDataSetSubJob::JOB_NAME,
                    LoadFileIntoDataSetSubJob::ENTRY_ID => $entryId,
                    LoadFileIntoDataSetSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
                ];
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