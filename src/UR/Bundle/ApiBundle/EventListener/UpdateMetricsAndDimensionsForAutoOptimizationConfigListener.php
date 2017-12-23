<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Bundle\ApiBundle\Behaviors\CalculateMetricsAndDimensionsTrait;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Worker\Manager;

class UpdateMetricsAndDimensionsForAutoOptimizationConfigListener
{
    use CalculateMetricsAndDimensionsTrait;

    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

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
            $this->updateMetricsAndDimensionsForAutoOptimizationConfig($entity);
        }
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     */
    protected function updateMetricsAndDimensionsForAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        $param = $this->paramsBuilder->buildFromAutoOptimizationConfig($autoOptimizationConfig);
        $columns = $this->getMetricsAndDimensionsForAutoOptimizationConfig($param);

        $autoOptimizationConfig->setMetrics($columns[self::METRICS_KEY]);
        $autoOptimizationConfig->setDimensions($columns[self::DIMENSIONS_KEY]);
    }

    protected function getMetricsKey()
    {
        return self::METRICS_KEY;
    }

    protected function getDimensionsKey()
    {
        return self::DIMENSIONS_KEY;
    }
}