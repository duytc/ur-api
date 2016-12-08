<?php


namespace UR\Service\Report;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\StringUtilTrait;

class ReportBuilder implements ReportBuilderInterface
{
    use StringUtilTrait;

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

    protected function getSingleReport(ParamsInterface $params, $overridingFilters = null)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();
        $joinBy = $params->getJoinByFields();
        $types = $params->getFieldTypes();

        /* get all metrics and dimensions from dataSets */
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }

            foreach ($dataSet->getDimensions() as $item) {
                if ($joinBy === $item) {
                    continue;
                }
                $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }
        }

        if (is_string($joinBy)) {
            $dimensions[] = $joinBy;
        }

        /* get all reports data */
        $data = $this->reportSelector->getReportData($params, $overridingFilters);
        $rows = $data[SqlBuilder::STATEMENT_KEY]->fetchAll();
        if (count($rows) < 1) {
            throw new NotFoundHttpException();
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows, $types);

        /* get final reports */
        $isSingleDataSet = count($dataSets) < 2;

        return $this->getFinalReports($collection, $params, $metrics, $dimensions, $data[SqlBuilder::DATE_RANGE_KEY], $isSingleDataSet, $joinBy);
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
        $types = [];
        $dateRanges = [];
        /* get all reports data */
        foreach ($reportViews as $reportView) {
            $view = $this->reportViewManager->find($reportView->getReportViewId());
            if (!$view instanceof ReportViewInterface) {
                throw new InvalidArgumentException(sprintf('The report view %d does not exist', $reportView->getReportViewId()));
            }

            $reportParam = $this->paramsBuilder->buildFromReportView($view);
            $filters = $reportView->getFilters();
            $result = $this->getSingleReport($reportParam, $filters);
            $types = array_merge($types, $result->getTypes());
            $dateRanges = array_merge($dateRanges, $result->getDateRange());
            $rows[] = $result->getTotal();
            $metrics = array_unique(array_merge($metrics, $reportView->getMetrics()));
            $dimensions = array_unique(array_merge($dimensions, $reportView->getDimensions()));
        }

        foreach($rows as &$row) {
            foreach($metrics as $metric) {
                if (!array_key_exists($metric, $row)) {
                    $row[$metric] = 0;
                }
            }

            foreach($row as $key=>$value) {
                if (!in_array($key, $metrics) && !in_array($key, $dimensions)) {
                    unset($row[$key]);
                }
            }
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows, $types);

        /* get final reports */
        return $this->getFinalReports($collection, $params, $metrics, $dimensions, $dateRanges);
    }

    /**
     * get final Reports, includes:
     * - transform report
     * - format report
     * - build columns will be showed in total
     * - group report
     *
     * @param Collection $reportCollection
     * @param ParamsInterface $params
     * @param array $metrics
     * @param array $dimensions
     * @param $dateRanges
     * @param bool $isSingleDataSet
     * @param $joinBy
     * @return mixed
     */
    private function getFinalReports(Collection $reportCollection, ParamsInterface $params, array $metrics, array $dimensions, $dateRanges, $isSingleDataSet = false, $joinBy = null)
    {
        /* transform data */
        $transforms = is_array($params->getTransforms()) ? $params->getTransforms() : [];
        $this->transformReports($reportCollection, $transforms, $metrics, $dimensions, $joinBy);

        /* build columns that will be showed in total */
        $showInTotal = is_array($params->getShowInTotal()) ? $params->getShowInTotal() : [];
        $showInTotal = $this->getShowInTotal($showInTotal, $metrics);

        /* group reports */
        /** @var ReportResultInterface $reportResult */
        $reportResult = $this->reportGrouper->group($reportCollection, $showInTotal, $params->getWeightedCalculations(), $dateRanges, $isSingleDataSet);

        /* format data */
        /** @var FormatInterface[] $formats */
        $formats = is_array($params->getFormats()) ? $params->getFormats() : [];
        $this->formatReports($reportResult, $formats, $metrics, $dimensions);

        /* return report result */
        return $reportResult;
    }

    /**
     * transform reports
     *
     * @param Collection $reportCollection
     * @param array $transforms
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     */
    private function transformReports(Collection $reportCollection, array $transforms, array &$metrics, array &$dimensions, $joinBy = null)
    {
        // sort transform by priority
        usort($transforms, function (TransformInterface $a, TransformInterface $b) {
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        /**
         * @var TransformInterface $transform
         */
        foreach ($transforms as $transform) {
            $transform->transform($reportCollection, $metrics, $dimensions, $joinBy);
        }
    }

    /**
     * format reports
     *
     * @param ReportResultInterface $reportResult
     * @param array $formats
     * @param array $metrics
     * @param array $dimensions
     */
    private function formatReports(ReportResultInterface $reportResult, array $formats, array $metrics, array $dimensions)
    {
        // sort format by priority
        usort($formats, function (FormatInterface $a, FormatInterface $b) {
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        foreach ($formats as $format) {
            if (!($format instanceof FormatInterface)) {
                continue;
            }

            $format->format($reportResult, $metrics, $dimensions);
        }
    }

    /**
     * get columns will be showed in total
     *
     * @param $showInTotal
     * @param array $metrics
     * @return array
     */
    private function getShowInTotal($showInTotal, array $metrics)
    {
        if (empty($showInTotal)) {
            $showInTotal = $metrics;
        }

        return $showInTotal;
    }
}