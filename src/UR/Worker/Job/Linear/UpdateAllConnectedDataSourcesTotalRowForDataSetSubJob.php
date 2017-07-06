<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\DataSet\UpdateDataSetTotalRowService;

class UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob implements SubJobInterface
{
    const JOB_NAME = 'updateAllConnectedDataSourcesTotalRowForDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $updateDataSetTotalRowService;

    public function __construct(LoggerInterface $logger, UpdateDataSetTotalRowService $updateDataSetTotalRowService)
    {
        $this->logger = $logger;
        $this->updateDataSetTotalRowService = $updateDataSetTotalRowService;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // we can process update total row one time after a batch of files are loaded
        // this can save a lot of processing time during linear load
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);

        if (!is_integer($dataSetId)) {
            $this->logger->error(sprintf('data set with ID (%s) not is an Integer when update all connected data source total row', $dataSetId));
            return;
        }

        $this->updateDataSetTotalRowService->updateAllConnectedDataSourcesTotalRowInOneDataSet($dataSetId);
    }
}