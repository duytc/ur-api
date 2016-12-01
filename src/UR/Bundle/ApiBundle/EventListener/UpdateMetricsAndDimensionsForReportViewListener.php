<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\DataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\StringUtilTrait;
use UR\Worker\Manager;

class UpdateMetricsAndDimensionsForReportViewListener
{
    use StringUtilTrait;
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


    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$entity instanceof ReportViewInterface) {
            return;
        }

        $this->updateMetricsAndDimensionsForReportView($entity, $em);
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$entity instanceof ReportViewInterface) {
            return;
        }

        if ($args->hasChangedField('dataSets') || $args->hasChangedField('transforms')) {
            $this->updateMetricsAndDimensionsForReportView($entity, $em);
        }
    }

    protected function updateMetricsAndDimensionsForReportView(ReportViewInterface $reportView, EntityManagerInterface $em)
    {
        $param = $this->paramsBuilder->buildFromReportView($reportView);
        $columns = $this->getMetricsAndDimensions($param);

        $reportView->setMetrics($columns[self::METRICS_KEY]);
        $reportView->setDimensions($columns[self::DIMENSIONS_KEY]);
    }

    public function getMetricsAndDimensions(ParamsInterface $params)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();
        $joinBy = $params->getJoinByFields();
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[] = $item;
            }

            foreach ($dataSet->getDimensions() as $item) {
                if ($joinBy === $this->removeIdSuffix($item)) {
                    continue;
                }

                $dimensions[] = $item;
            }
        }

        if (is_string($joinBy)) {
            $dimensions[] = $joinBy;
        }

        $transforms = $params->getTransforms();
        /**
         * @var TransformInterface $transform
         * @var ReportViewInterface $entity
         */
        foreach ($transforms as $transform) {
            $transform->getMetricsAndDimensions($metrics, $dimensions);
        }

        return array (
            self::METRICS_KEY => $metrics,
            self::DIMENSIONS_KEY => $dimensions
        );
    }
}