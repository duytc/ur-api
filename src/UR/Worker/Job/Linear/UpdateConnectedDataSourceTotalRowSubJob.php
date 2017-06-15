<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\UpdateDataSetTotalRowService;

class UpdateConnectedDataSourceTotalRowSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'updateConnectedDataSourceTotalRowSubJob';

    const DATA_SET_ID = 'data_set_id';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $updateDataSetTotalRowService;

    private $connectedDataSourceManager;

    public function __construct(LoggerInterface $logger, UpdateDataSetTotalRowService $updateDataSetTotalRowService, ConnectedDataSourceManagerInterface $connectedDataSourceManager)
    {
        $this->logger = $logger;
        $this->updateDataSetTotalRowService = $updateDataSetTotalRowService;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // do something here

        // we can process update total row one time after a batch of files are loaded
        // this can save a lot of processing time during linear load
        $connectedDataSourceId = $params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        // update total rows of connected data source
        if ($connectedDataSource instanceof ConnectedDataSourceInterface) {
            $this->logger->notice('updating total row for connected data sources');
            $this->updateDataSetTotalRowService->updateConnectedDataSourceTotalRow($connectedDataSource);
            $this->logger->notice('success update total row connected data sources ');
        }
    }
}