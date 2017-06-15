<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use Symfony\Component\Process\Process;
use UR\Service\Command\CommandService;
use UR\Service\DataSet\UpdateDataSetTotalRowService;

class UpdateDataSetTotalRowSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'updateDataSetTotalRowSubJob';

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
        // do something here

        // we can process update total row one time after a batch of files are loaded
        // this can save a lot of processing time during linear load
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);

        if (!is_integer($dataSetId)) {
            return;
        }

        $this->updateDataSetTotalRowService->updateDataSetTotalRow($dataSetId);
    }
}