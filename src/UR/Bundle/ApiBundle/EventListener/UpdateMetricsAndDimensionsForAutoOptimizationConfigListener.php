<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Worker\Manager;

class UpdateMetricsAndDimensionsForAutoOptimizationConfigListener
{
    use CalculateMetricsAndDimensionsTrait;

    /**
     * @var ParamsBuilderInterface
     * @var Manager
     */
    protected $paramsBuilder;

    /**
     * UpdateMetricsAndDimensionsForReportViewListener constructor.
     * @param ParamsBuilderInterface $paramsBuilder
     */
    public function __construct(ParamsBuilderInterface $paramsBuilder)
    {
        $this->paramsBuilder = $paramsBuilder;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof AutoOptimizationConfigInterface) {
            return;
        }

        $this->updateMetricsAndDimensionsForAutoOptimizationConfig($entity);
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof AutoOptimizationConfigInterface) {
            return;
        }

        if ($args->hasChangedField('transform')) {
            //$this->updateMetricsAndDimensionsForAutoOptimizationConfig($entity);
        }
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     */
    protected function updateMetricsAndDimensionsForAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        $param = $this->paramsBuilder->buildFromAutoOptimizationConfig($autoOptimizationConfig);
        $columns = $this->getMetricsAndDimensionsForAutoOptimizationConfig($param);

        $autoOptimizationConfig->setMetrics(array_values($columns[DataSetInterface::METRICS_COLUMN]));
        $autoOptimizationConfig->setDimensions(array_values($columns[DataSetInterface::DIMENSIONS_COLUMN]));
    }

    protected function getMetricsKey()
    {
        return DataSetInterface::METRICS_COLUMN;
    }

    protected function getDimensionsKey()
    {
        return DataSetInterface::DIMENSIONS_COLUMN;
    }
}