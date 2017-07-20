<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\DataSet\DataMappingService;

class LoadFileIntoDataSetMapBuilderSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'loadFileIntoDataSetMapBuilderSubJob';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DataMappingService */
    private $dataMappingService;

    public function __construct(LoggerInterface $logger, DataMappingService $dataMappingService)
    {
        $this->logger = $logger;
        $this->dataMappingService = $dataMappingService;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);
        return $this->dataMappingService->importDataFromMapBuilderConfig($dataSetId);
    }
}