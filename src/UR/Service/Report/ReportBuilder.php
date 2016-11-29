<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Collection;

class ReportBuilder implements ReportBuilderInterface
{
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

    /**
     * @var ReportSelectorInterface
     */
    protected $reportSelector;

    /**
     * @var ReportGrouperInterface
     */
    protected $reportGrouper;

    /**
     * @var ReportViewManagerInterface
     */
    protected $reportViewManager;

    /**
     * @var ParamsBuilderInterface
     */
    protected $paramsBuilder;

    /**
     * ReportBuilder constructor.
     * @param ReportSelectorInterface $reportSelector
     * @param ReportGrouperInterface $reportGrouper
     * @param ReportViewManagerInterface $reportViewManager
     * @param ParamsBuilderInterface $paramsBuilder
     */
    public function __construct(ReportSelectorInterface $reportSelector, ReportGrouperInterface $reportGrouper,
        ReportViewManagerInterface $reportViewManager, ParamsBuilderInterface $paramsBuilder)
    {
        $this->reportSelector = $reportSelector;
        $this->reportGrouper = $reportGrouper;
        $this->reportViewManager = $reportViewManager;
        $this->paramsBuilder = $paramsBuilder;
    }

    public function getReport(ParamsInterface $params)
    {
        if ($params->isMultiView()) {
            return $this->getMultipleReport($params);
        }

        return $this->getSingleReport($params);
    }

    protected function getSingleReport(ParamsInterface $params)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();

        /* get all metrics and dimensions from dataSets */
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }

            foreach ($dataSet->getDimensions() as $item) {
                $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }
        }

        /* get all reports data */
        $statement = $this->reportSelector->getReportData($params);
        $collection = new Collection(array_merge($metrics, $dimensions), $statement->fetchAll());

        /* transform data */
        $transforms = $params->getTransforms();

        // sort transform by priority
        usort($transforms, function(TransformInterface $a, TransformInterface $b){
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        /**
         * @var TransformInterface $transform
         */
        foreach ($transforms as $transform) {
            $transform->transform($collection, $metrics, $dimensions);
        }

        $showInTotal = $params->getShowInTotal();
        if (empty($showInTotal)) {
            $showInTotal = $metrics;
        }
        return $this->reportGrouper->group($collection, $showInTotal, $params->getWeightedCalculations());
    }

    protected function getMultipleReport(ParamsInterface $params)
    {
        $reportViews = $params->getReportViews();
        if (empty($reportViews)) {
            throw new NotFoundHttpException('can not find the report');
        }

        $rows = [];
        $dimensions = [];
        $metrics = [];
        foreach($reportViews as $reportViewId) {
            $reportView = $this->reportViewManager->find($reportViewId);
            if (!$reportView instanceof ReportViewInterface) {
                throw new InvalidArgumentException(sprintf('The report view %d does not exist', $reportViewId));
            }

            $reportParam = $this->paramsBuilder->buildFromReportView($reportView);
            $result = $this->getReport($reportParam);
            $rows[] = $result->getTotal();
            $metrics = array_unique(array_merge($metrics, $reportView->getMetrics()));
            $dimensions = array_unique(array_merge($dimensions, $reportView->getDimensions()));
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows);

        $transforms = $params->getTransforms();
        // sort transform by priority
        usort($transforms, function(TransformInterface $a, TransformInterface $b){
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        /**
         * @var TransformInterface $transform
         */
        foreach ($transforms as $transform) {
            $transform->transform($collection, $metrics, $dimensions);
        }

        $showInTotal = $params->getShowInTotal();
        if (empty($showInTotal)) {
            $showInTotal = $metrics;
        }
        
        /* format data */
        /** @var FormatInterface[] $formats */
        $formats = $params->getFormats();

        foreach ($formats as $format) {
            $format->format($collection, $metrics, $dimensions);
        }

        /* group data */
        return $this->reportGrouper->group($collection, $showInTotal, $params->getWeightedCalculations());
    }
}