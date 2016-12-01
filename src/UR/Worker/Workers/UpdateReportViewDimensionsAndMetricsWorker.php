<?php


namespace UR\Worker\Workers;


use stdClass;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\StringUtilTrait;

class UpdateReportViewDimensionsAndMetricsWorker
{
    use StringUtilTrait;
    use ColumnUtilTrait;
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    /**
     * @var ReportViewManagerInterface
     */
    protected $reportViewManager;

    /**
     * @var ParamsBuilderInterface
     */
    protected $paramsBuilder;

    /**
     * UpdateReportViewDimensionsAndMetrics constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param ReportViewManagerInterface $reportViewManager
     * @param ParamsBuilderInterface $paramsBuilder
     */
    public function __construct(DataSetManagerInterface $dataSetManager, ReportViewManagerInterface $reportViewManager, ParamsBuilderInterface $paramsBuilder)
    {
        $this->dataSetManager = $dataSetManager;
        $this->reportViewManager = $reportViewManager;
        $this->paramsBuilder = $paramsBuilder;
    }

    public function updateDimensionsAndMetricsForReportView(StdClass $param)
    {
        $id = $param->id;
        $reportView = $this->dataSetManager->find($id);

        if (!$reportView instanceof ReportViewInterface) {
            throw new InvalidArgumentException(sprintf('The report view %d does not exist', $id));
        }

        $params = $this->paramsBuilder->buildFromReportView($reportView);
        $columns = $this->getMetricsAndDimensions($params);

        $reportView->setMetrics($columns[self::METRICS_KEY]);
        $reportView->setDimensions($columns[self::DIMENSIONS_KEY]);

        $this->reportViewManager->save($reportView);
    }

    protected function getMetricsAndDimensions(ParamsInterface $params)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();
        $joinBy = $params->getJoinByFields();
        $singleDataSet = count($dataSets) < 2;
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[sprintf('%s_%d', $item, $dataSet->getDataSetId())] = $this->convertColumn($item, $singleDataSet);
            }

            foreach ($dataSet->getDimensions() as $item) {
                if ($joinBy === $this->removeIdSuffix($item)) {
                    continue;
                }

                $dimensions[sprintf('%s_%d', $item, $dataSet->getDataSetId())] = $this->convertColumn($item, $singleDataSet);
            }
        }

        if (is_string($joinBy)) {
            $dimensions[$joinBy] = $this->convertColumn($joinBy, $singleDataSet);
        }

        $transforms = $params->getTransforms();
        /**
         * @var TransformInterface $transform
         */
        foreach ($transforms as $transform) {
            $transform->getMetricsAndDimensions($metrics, $dimensions);
        }

        return array (
            self::METRICS_KEY => $metrics,
            self::DIMENSIONS_KEY => $dimensions
        );
    }

    protected function getDataSetManager()
    {
        return $this->dataSetManager;
    }
}