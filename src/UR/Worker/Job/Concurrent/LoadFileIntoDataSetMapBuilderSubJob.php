<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\DataSet\DataMappingService;
use UR\Worker\Job\Linear\SubJobInterface;

class LoadFileIntoDataSetMapBuilderSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'loadFileIntoDataSetMapBuilderSubJob';

    const DATA_SET_ID = 'data_set_id';
    const MAP_BUILDER_CONFIG_ID = 'map_builder_config_id';

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
        $mapBuilderConfigId = $params->getRequiredParam(self::MAP_BUILDER_CONFIG_ID);

        return $this->dataMappingService->importDataFromMapBuilderConfig($dataSetId, $mapBuilderConfigId);
    }
}