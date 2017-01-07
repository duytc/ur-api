<?php


namespace UR\Service\Report;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Domain\DTO\Report\DateRange;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Domain\DTO\Report\ReportViews\ReportViewInterface as ReportViewDTO;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\StringUtilTrait;
use UR\Service\DataSet\Type;

class ReportBuilder implements ReportBuilderInterface
{
    use StringUtilTrait;

    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';
    const SUB_VIEW_FIELD_KEY = 'report_view';
    const REPORT_VIEW_ALIAS = 'report_view_alias';
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

    /**
     * @inheritdoc
     */
    public function getShareableReport(ParamsInterface $params, array $fieldsToBeShared)
    {
        /**
         * @var ReportResultInterface $reportResult
         * [
         *      average => [],
         *      columns => [],
         *      dateRange => '',
         *      reports => [],
         *      total => [],
         *      types => []
         * ]
         */
        $reportResult = $this->getReport($params);

        // check if $fieldsToBeShared not yet configured (empty array) => default share all
        if (count($fieldsToBeShared) < 1) {
            return $reportResult;
        }

        // unset fields not to be shared
        $average = $reportResult->getAverage();
        $columns = $reportResult->getColumns();
        $reports = $reportResult->getReports();
        $total = $reportResult->getTotal();
        $types = $reportResult->getTypes();

        if (is_array($average)) {
            $average = $this->filterFieldsInArray($fieldsToBeShared, $average);
            $reportResult->setAverage($average);
        }

        if (is_array($columns)) {
            $columns = $this->filterFieldsInArray($fieldsToBeShared, $columns);
            $reportResult->setColumns($columns);
        }

        if (is_array($reports)) {
            foreach ($reports as &$report) {
                $report = $this->filterFieldsInArray($fieldsToBeShared, $report);
            }
            $reportResult->setReports($reports);
        }

        if (is_array($total)) {
            $total = $this->filterFieldsInArray($fieldsToBeShared, $total);
            $reportResult->setTotal($total);
        }

        if (is_array($types)) {
            $types = $this->filterFieldsInArray($fieldsToBeShared, $types);
            $reportResult->setTypes($types);
        }

        return $reportResult;
    }

    protected function getSingleReport(ParamsInterface $params, $overridingFilters = null, $isNeedFormatReport = true)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();
        $joinByConfig = $params->getJoinByFields();
        $types = $params->getFieldTypes();

        /* parse joinBy config */
        $flattenJoinFields = [];
        $outputJoinField = null;
        if (is_array($joinByConfig) && count($joinByConfig) > 0 && array_key_exists('joinFields', $joinByConfig[0]) && array_key_exists('outputField', $joinByConfig[0])) {
            $joinFields = $joinByConfig[0]['joinFields'];
            $outputJoinField = $joinByConfig[0]['outputField'];

            if (is_array($joinFields)) {
                $flattenJoinFields = array_map(function ($joinField) {
                    return (!array_key_exists('field', $joinField) || !array_key_exists('field', $joinField))
                        ? ''
                        : sprintf('%s_%d', $joinField['field'], $joinField['dataSet']);
                }, $joinFields);
            }
        }

        /* get all metrics and dimensions from dataSets */
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }

            foreach ($dataSet->getDimensions() as $item) {
                $itemWithDataSetId = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                if (in_array($itemWithDataSetId, $flattenJoinFields)) {
                    continue;
                }

                $dimensions[] = $itemWithDataSetId;
            }
        }

        if (is_string($outputJoinField)) {
            $dimensions[] = $outputJoinField;
        }

        /* get all reports data */
        $data = $this->reportSelector->getReportData($params, $overridingFilters);
        $rows = $data[SqlBuilder::STATEMENT_KEY]->fetchAll();
        if (count($rows) < 1) {
            return new ReportResult([], [], [], []);
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows, $types);

        /* get final reports */
        $isSingleDataSet = count($dataSets) < 2;

        return $this->getFinalReports($collection, $params, $metrics, $dimensions, $data[SqlBuilder::DATE_RANGE_KEY], $isSingleDataSet, $outputJoinField, $isNeedFormatReport);
    }

    protected function getMultipleReport(ParamsInterface $params)
    {
        $rows = [];
        $dimensions = [];
        $metrics = [];
        $types = [];
        $dateRanges = [];

        $reportViews = $params->getReportViews();
        $subReport = $params->isSubReportIncluded();
        if (empty($reportViews)) {
            throw new NotFoundHttpException('can not find the report');
        }

        /* get all reports data */

        /**
         * @var ReportViewDTO $reportView
         */
        foreach ($reportViews as $reportView) {
            $view = $this->reportViewManager->find($reportView->getReportViewId());
            if (!$view instanceof ReportViewInterface) {
                throw new InvalidArgumentException(sprintf('The report view %d does not exist', $reportView->getReportViewId()));
            }

            $reportParam = $this->paramsBuilder->buildFromReportView($view);
            $viewFilters = $reportView->getFilters();
            $result = $this->getSingleReport($reportParam, $isNeedFormatReport = false); // do not format report to avoid error when doing duplicate format
            if (count($result->getReports()) < 1) {
                continue;
            }

            /**
             * @var FilterInterface $viewFilter
             */
            foreach ($viewFilters as $viewFilter) {
                $result = $viewFilter->filter($result);
            }

            $types = array_merge($types, $result->getTypes());
            if ($subReport === true) {
                $reports = $result->getReports();
                $reports = $this->addNewField(self::REPORT_VIEW_ALIAS, $view->getAlias(), $reports);
                $rows = array_merge($rows, $reports);
            } else {
                $total = $result->getTotal();
                $total = array_merge($total, array(self::REPORT_VIEW_ALIAS => $view->getAlias()));
                $rows[] = $total;
            }

            $metrics = array_unique(array_merge($metrics, $reportView->getMetrics()));
            $dimensions = array_unique(array_merge($dimensions, $reportView->getDimensions()));

            $dateRange = $result->getDateRange();
            if (!$dateRange instanceof DateRange) {
                continue;
            }
            $dateRanges[] = $dateRange;
        }

        $dimensions[] = self::REPORT_VIEW_ALIAS;
        $types[self::REPORT_VIEW_ALIAS] = Type::TEXT;

        foreach ($rows as &$row) {
            foreach ($metrics as $metric) {
                if (!array_key_exists($metric, $row)) {
                    //$row[$metric] = null;
                    continue;
                }
            }

            foreach ($dimensions as $dimension) {
                if (!array_key_exists($dimension, $row)) {
                    //$row[$dimension] = null;
                    continue;
                }
            }

            foreach ($row as $key => $value) {
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
     * @param bool $isNeedFormatReport
     * @return mixed
     */
    private function getFinalReports(Collection $reportCollection, ParamsInterface $params, array $metrics, array $dimensions, $dateRanges, $isSingleDataSet = false, $joinBy = null, $isNeedFormatReport = true)
    {
        /* transform data */
        $transforms = is_array($params->getTransforms()) ? $params->getTransforms() : [];
        $this->transformReports($reportCollection, $transforms, $metrics, $dimensions, $joinBy);

        /* build columns that will be showed in total */
        $showInTotal = is_array($params->getShowInTotal()) ? $params->getShowInTotal() : $metrics;
//      $showInTotal = $this->getShowInTotal($showInTotal, $metrics);

        /* group reports */
        /** @var ReportResultInterface $reportResult */
        $reportResult = $this->reportGrouper->group($reportCollection, $showInTotal, $params->getWeightedCalculations(), $dateRanges, $isSingleDataSet);

	    /*update column after group */
	   /* $firstReport = ($reportResult->getReports())[0];
	    $columnNameInReport = array_keys($firstReport);
	    $mappedColumns = $reportResult->getColumns();

	    foreach ($mappedColumns as $columnName=>$value) {
		    if (!in_array($columnName, $columnNameInReport)) {
			    unset($mappedColumns[$columnName]);
		    }
	    }
	    $reportResult->setColumns($mappedColumns);*/

        /* format data if need */
        if ($isNeedFormatReport) {
            /** @var FormatInterface[] $formats */
            $formats = is_array($params->getFormats()) ? $params->getFormats() : [];
            $this->formatReports($reportResult, $formats, $metrics, $dimensions);
        }

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
     * @param $key
     * @param $value
     * @param array $arrays
     * @return mixed
     */
    private function addNewField($key, $value, array $arrays)
    {
        return array_map(function (array $array) use ($key, $value) {
            $array[$key] = $value;
            return $array;
        }, $arrays);
    }

    /**
     * filter keep fields in array
     * @param array $fields
     * @param array $array
     * @return array
     */
    private function filterFieldsInArray(array $fields, array $array)
    {
        $result = array_filter(
            $array,
            function ($key) use ($fields) {
                return in_array($key, $fields);
            },
            ARRAY_FILTER_USE_KEY
        );

        return $result;
    }
}