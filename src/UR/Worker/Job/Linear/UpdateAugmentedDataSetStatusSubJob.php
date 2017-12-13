<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;

class UpdateAugmentedDataSetStatusSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'UpdateAugmentedDataSetStatusSubJob';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    /** @var  LinkedMapDataSetRepositoryInterface */
    private $linkedMapDataSetRepository;

    public function __construct(LoggerInterface $logger, DataSetManagerInterface $dataSetManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager,
            LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository)
    {
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->linkedMapDataSetRepository = $linkedMapDataSetRepository;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);

        if (!is_integer($dataSetId)) {
            return;
        }

        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSet($dataSet);

        if (empty($linkedMapDataSets)) {
            return;
        }

        /** @var LinkedMapDataSetInterface $linkedMapDataSet */
        foreach ($linkedMapDataSets as $linkedMapDataSet) {
            $dataSet = $linkedMapDataSet->getConnectedDataSource()->getDataSet();
            $dataSet->setNumChanges($dataSet->getNumChanges() + 1);
            $this->dataSetManager->save($dataSet);

            $connectedDataSource = $linkedMapDataSet->getConnectedDataSource();
            $connectedDataSource->setNumChanges($connectedDataSource->getNumChanges() + 1);
            $this->connectedDataSourceManager->save($connectedDataSource);
        }
    }
}