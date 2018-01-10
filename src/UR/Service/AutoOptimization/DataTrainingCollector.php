<?php

namespace UR\Service\AutoOptimization;

use UR\Behaviors\AutoOptimizationUtilTrait;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DTO\IdentifierGeneratorInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\Report\ReportBuilderInterface;

class DataTrainingCollector implements DataTrainingCollectorInterface
{
    use AutoOptimizationUtilTrait;

    /** @var ParamsBuilderInterface */
    private $paramsBuilder;

    /** @var ReportBuilderInterface */
    private $reportBuilder;

    /** @var DataTrainingTableServiceInterface  */
    private $dataTrainingTableService;

    /**
     * DataTrainingCollector constructor.
     * @param ParamsBuilderInterface $paramsBuilder
     * @param ReportBuilderInterface $reportBuilder
     * @param DataTrainingTableServiceInterface $dataTrainingTableService
     */
    public function __construct(ParamsBuilderInterface $paramsBuilder, ReportBuilderInterface $reportBuilder, DataTrainingTableServiceInterface $dataTrainingTableService)
    {
        $this->paramsBuilder = $paramsBuilder;
        $this->reportBuilder = $reportBuilder;
        $this->dataTrainingTableService = $dataTrainingTableService;
    }

    /**
     * @inheritdoc
     */
    public function buildDataForAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        $params = $this->paramsBuilder->buildFromAutoOptimizationConfig($autoOptimizationConfig);
        $result = $this->reportBuilder->getReport($params);

        if (!$result instanceof ReportResultInterface) {
            return $result;
        }

        $result = $this->addIdentifiersToTrainingData($result, $autoOptimizationConfig);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getDataByIdentifiers(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers)
    {
        return $this->dataTrainingTableService->getDataByIdentifiers($autoOptimizationConfig, $identifiers);
    }

    /**
     * @param ReportResultInterface $result
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return ReportResultInterface
     */
    private function addIdentifiersToTrainingData(ReportResultInterface $result, AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        $identifierGenerators = $autoOptimizationConfig->getIdentifierObjects();

        if (empty($identifierGenerators) || !is_array($identifierGenerators)) {
            return $result;
        }

        $collection = new Collection($result->getColumns(), $result->getRows(), $result->getTypes());

        foreach ($identifierGenerators as $identifierGenerator) {
            if (!$identifierGenerator instanceof IdentifierGeneratorInterface) {
                continue;
            }

            $collection = $identifierGenerator->generateIdentifiers($collection);
        }

        $result->setColumns($collection->getColumns());
        $result->setRows($collection->getRows());
        $result->setTypes($collection->getTypes());

        return $result;
    }
}