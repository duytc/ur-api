<?php

namespace UR\Service\Report;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Domain\DTO\Report\DataSets\DataSetInterface as DataSetDTO;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\DateRange;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\JoinBy\JoinFieldInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportViews\ReportViewInterface as ReportViewDTO;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\StringUtilTrait;

class ReportBuilder implements ReportBuilderInterface
{
    use StringUtilTrait;

    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';
    const SUB_VIEW_FIELD_KEY = 'report_view';
    const REPORT_VIEW_ALIAS = 'report_view_alias';

    const TYPE_DATE = 'date';
    const TYPE_DATE_TIME = 'dateTime';
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
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    /**
     * ReportBuilder constructor.
     * @param ReportSelectorInterface $reportSelector
     * @param ReportGrouperInterface $reportGrouper
     * @param ReportViewManagerInterface $reportViewManager
     * @param ParamsBuilderInterface $paramsBuilder
     */
    public function __construct(ReportSelectorInterface $reportSelector, ReportGrouperInterface $reportGrouper,
                                ReportViewManagerInterface $reportViewManager, ParamsBuilderInterface $paramsBuilder, DataSetManagerInterface $dataSetManager)
    {
        $this->reportSelector = $reportSelector;
        $this->reportGrouper = $reportGrouper;
        $this->reportViewManager = $reportViewManager;
        $this->paramsBuilder = $paramsBuilder;
        $this->dataSetManager = $dataSetManager;
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

    public function getReport(ParamsInterface $params)
    {
        if ($params->isMultiView()) {
            return $this->getMultipleReport($params);
        }

        return $this->getSingleReport($params);
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

        $startDate = $params->getStartDate();
        $endDate = $params->getEndDate();

        if (!empty($startDate) && !empty($endDate)) {
            foreach ($reportViews as $reportView) {
                $this->overrideDateTypeFiltersForReportView($reportView, $startDate, $endDate);
            }
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

            if ($reportParam->getStartDate() === null && $reportParam->getEndDate() === null) {
                $result = $this->getSingleReport($reportParam, null, $isNeedFormatReport = false); // do not format report to avoid error when doing duplicate format
                if (count($result->getReports()) < 1) {
                    continue;
                }
                /**
                 * @var FilterInterface $viewFilter
                 */
                foreach ($viewFilters as $viewFilter) {
                    $result = $viewFilter->filter($result);
                }
            } else {
                $reportParam->setStartDate($startDate);
                $reportParam->setEndDate($endDate);

                $result = $this->getSingleReport($reportParam, $viewFilters, $isNeedFormatReport = false); // do not format report to avoid error when doing duplicate format
                if (count($result->getReports()) < 1) {
                    continue;
                }
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
        $types[self::REPORT_VIEW_ALIAS] = FieldType::TEXT;

        foreach ($rows as &$row) {
            foreach ($metrics as $metric) {
                if (!array_key_exists($metric, $row)) {
                    $row[$metric] = null;
                }
            }

            foreach ($dimensions as $dimension) {
                if (!array_key_exists($dimension, $row)) {
                    $row[$dimension] = null;
                }
            }

            // filter all fields in row if key is not in dimensions and metrics of report view
            //foreach ($row as $key => $value) {
            //    if (!in_array($key, $metrics) && !in_array($key, $dimensions)) {
            //        unset($row[$key]);
            //    }
            //}
            // but now we do not filter because we allow non-selected fields are used in addCalculatedField transformer.
            // So, after that we need to filter non-selected fields from final reports
        }

        if (count($rows) == 0) {
            return false;
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows, $types);

        /* get final reports */
        return $this->getFinalReports($collection, $params, $metrics, $dimensions, $dateRanges);
    }

    /**
     * @param ReportViewDTO $reportView
     * @param $startDate
     * @param $endDate
     * @return ReportViewDTO
     */
    protected function overrideDateTypeFiltersForReportView(ReportViewDTO $reportView, $startDate, $endDate)
    {
        $filters = $reportView->getFilters();
        /** @var FilterInterface|DateFilterInterface $filter */
        foreach ($filters as $filter) {
            if (!$filter instanceof DateFilterInterface) {
                continue;
            }

            if ($filter->isUserDefine()) {
                $filter->setDateValue(array(
                    DateFilter::DATE_VALUE_FILTER_START_DATE_KEY => $startDate,
                    DateFilter::DATE_VALUE_FILTER_END_DATE_KEY => $endDate
                ));
            }
        }

        $reportView->setFilters($filters);

        return $reportView;
    }

    protected function getSingleReport(ParamsInterface $params, $overridingFilters = null, $isNeedFormatReport = true)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();
        $joinConfig = $params->getJoinConfigs();
        $types = $params->getFieldTypes();

        $startDate = $params->getStartDate();
        $endDate = $params->getEndDate();

        /* parse joinBy config */
        $flattenJoinFields = [];
        $outputJoinFields = [];

        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            /** @var JoinFieldInterface $joinField */
            foreach ($config->getJoinFields() as $joinField) {
                $fields = explode(',', $joinField->getField());
                foreach ($fields as $item) {
                    $flattenJoinFields[] = sprintf('%s_%d', $item, $joinField->getDataSet());
                }
            }

            if ($config->isVisible() && !in_array($config->getOutputField(), $outputJoinFields)) {
                $outputJoinFields = array_merge($outputJoinFields, explode(',', $config->getOutputField()));
            }
        }

        if ($startDate instanceof \DateTime && $endDate instanceof \DateTime) {
            foreach ($dataSets as $dataSet) {
                $this->overrideDateTypeFilterForDataSet($dataSet, $startDate, $endDate);
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

        $dimensions = array_merge($dimensions, $outputJoinFields);

        /*
         * get all reports data
         * Notice: reports data contains all fields in Data Set, i.e both selected and non-selected fields in report view.
         * So, after that we need filter all non-selected fields from reports data
         */
        $data = $this->reportSelector->getReportData($params, $overridingFilters);
        $rows = $data[SqlBuilder::STATEMENT_KEY]->fetchAll();
        if (count($rows) < 1) {
            return new ReportResult([], [], [], []);
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows, $types);

        /* get final reports */
        $isSingleDataSet = count($dataSets) < 2;

        return $this->getFinalReports($collection, $params, $metrics, $dimensions, $data[SqlBuilder::DATE_RANGE_KEY], $isSingleDataSet, $outputJoinFields, $isNeedFormatReport);
    }

    /**
     * @param DataSetDTO $dataSet
     * @param $startDate
     * @param $endDate
     * @return DataSetDTO
     */
    protected function overrideDateTypeFilterForDataSet(DataSetDTO &$dataSet, \DateTime $startDate, \DateTime $endDate)
    {
        $filters = $dataSet->getFilters();

        /** @var FilterInterface|DateFilterInterface $filter */
        foreach ($filters as $filter) {
            if (!$filter instanceof DateFilterInterface) {
                continue;
            }

            if ($filter->isUserDefine()) {
                $filter->setDateValue(array(
                    DateFilter::DATE_VALUE_FILTER_START_DATE_KEY => $startDate->format('Y-m-d'),
                    DateFilter::DATE_VALUE_FILTER_END_DATE_KEY => $endDate->format('Y-m-d')
                ));
            }
        }

        $dataSet->setFilters($filters);

        return $dataSet;
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
     * @param $outputJoinField
     * @param bool $isNeedFormatReport
     * @return mixed
     */
    private function getFinalReports(Collection $reportCollection, ParamsInterface $params, array $metrics, array $dimensions, $dateRanges, $isSingleDataSet = false, array $outputJoinField = [], $isNeedFormatReport = true)
    {
        /* transform data */
        $transforms = is_array($params->getTransforms()) ? $params->getTransforms() : [];
        if (!empty($params->getUserDefinedDimensions()) && $params->isNeedToGroup()) {
            if (count($transforms) > 0) {
                $groupByTransforms = array_filter($transforms, function ($transform) {
                    return $transform instanceof GroupByTransform;
                });

                if (count($groupByTransforms) < 1) {
                    $transforms[] = new GroupByTransform(
                        $params->getUserDefinedDimensions()
                    );
                } else {
                    foreach ($groupByTransforms as &$groupByTransform) {
                        $allDimensionMetrics = array_merge($params->getUserDefinedDimensions(), $params->getUserDefinedMetrics());

                        /** @var GroupByTransform $groupByTransform */
                        $transformFields = $groupByTransform->getFields();
                        $transformFields = array_filter($transformFields, function ($field) use ($allDimensionMetrics) {
                            return in_array($field, $allDimensionMetrics);
                        });

                        if (count($transformFields) < 1) {
                            $transformFields = $params->getUserDefinedDimensions();
                        }

                        $groupByTransform->setFields(array_unique($transformFields));
                    }
                }
            } else {
                $transforms[] = new GroupByTransform(
                    $params->getUserDefinedDimensions()
                );
            }
        }

        $this->transformReports($reportCollection, $transforms, $metrics, $dimensions, $outputJoinField);

        /* build columns that will be showed in total */
        $showInTotal = is_array($params->getShowInTotal()) ? $params->getShowInTotal() : $metrics;

        /** @var JoinConfigInterface[] $joinConfig */
        $joinConfig = $params->getJoinConfigs();
        $hiddenJoinFields = [];
        foreach ($joinConfig as $config) {
            if (!$config->isVisible()) {
                $hiddenJoinFields[] = $config->getOutputField();
            }
        }

        $reports = $reportCollection->getRows();
        foreach ($reports as &$report) {
            foreach ($hiddenJoinFields as $hiddenJoinField) {
                unset($report[$hiddenJoinField]);
            }
            unset($report);
        }

        $reportCollection->setRows($reports);

        /* group reports */
        /** @var ReportResultInterface $reportResult */
        $reportResult = $this->reportGrouper->group($reportCollection, $showInTotal, $params->getWeightedCalculations(), $dateRanges, $params->getIsShowDataSetName(), $isSingleDataSet);

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

        /* Filter all non-selected fields from reports data */
        $nonSelectedFields = [];

        // get nonSelectedFields from data sets if single report view used multiple data set
        /** @var \UR\Domain\DTO\Report\DataSets\DataSetInterface[] $dataSets */
        $dataSets = $params->getDataSets();
        if (is_array($dataSets)) {
            $allDataSetFields = [];
            $selectedFields = [];

            foreach ($dataSets as $dataSet) {
                // get all fields from data set entity
                $dataSetId = $dataSet->getDataSetId();
                $dataSetEntity = $this->dataSetManager->find($dataSetId);
                if (!$dataSetEntity instanceof DataSetInterface) {
                    continue;
                }

                $allDataSetFieldsTmp = array_keys($dataSetEntity->getAllDimensionMetrics());

                // merge hidden fields from data set DTO to data set entity
                $allDataSetFieldsTmp = array_merge($allDataSetFieldsTmp, $dataSet->getDimensions(), $dataSet->getMetrics());
                $allDataSetFieldsTmp = array_values(array_unique($allDataSetFieldsTmp));

                // get all selected fields from data set DTO
                $metrics = $dataSet->getMetrics();
                $dimensions = $dataSet->getDimensions();
                $selectedFieldsTmp = array_merge($metrics, $dimensions);

                // convert field to field_<data set id>
                $allDataSetFieldsTmp = array_map(function ($item) use ($dataSet) {
                    return sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }, $allDataSetFieldsTmp);

                $selectedFieldsTmp = array_map(function ($item) use ($dataSet) {
                    return sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }, $selectedFieldsTmp);

                $allDataSetFields = array_merge($allDataSetFields, $allDataSetFieldsTmp);
                $selectedFields = array_merge($selectedFields, $selectedFieldsTmp);
            }

            $nonSelectedFields = array_diff($allDataSetFields, $selectedFields);
        }

        // get nonSelectedFields from sub-report-views if report-multi-views used multiple report views
        /** @var \UR\Domain\DTO\Report\ReportViews\ReportViewInterface[] $reportViews */
        $reportViews = $params->getReportViews();
        if (is_array($reportViews)) {
            $allSubReportFields = [];
            $selectedFields = [];

            foreach ($reportViews as $reportView) {
                // get all fields from report view entity
                $reportViewId = $reportView->getReportViewId();
                $reportViewEntity = $this->reportViewManager->find($reportViewId);
                if (!$reportViewEntity instanceof ReportViewInterface) {
                    continue;
                }

                $allReportViewFieldsTmp = array_merge($reportViewEntity->getDimensions(), $reportViewEntity->getMetrics());

                // get all selected fields from data set DTO
                $metrics = $reportView->getMetrics();
                $dimensions = $reportView->getDimensions();
                $selectedFieldsTmp = array_merge($metrics, $dimensions);

                $allSubReportFields = array_merge($allSubReportFields, $allReportViewFieldsTmp);
                $selectedFields = array_merge($selectedFields, $selectedFieldsTmp);
            }

            $nonSelectedFields = array_diff($allSubReportFields, $selectedFields);
        }

        // do filter
        $this->filterNonSelectedFieldsFromReports($reportResult, $nonSelectedFields);

        /* format data if need */
        if ($isNeedFormatReport) {
            /** @var FormatInterface[] $formats */
            $formats = is_array($params->getFormats()) ? $params->getFormats() : [];
            $this->formatReports($reportResult, $formats, $metrics, $dimensions);
        }

        $types = $reportResult->getTypes();
        $temporaryFields = [];
        foreach ($types as $column => $type) {
            if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                $pos = strpos($column, "_");
                $fieldPattern = "__" . substr($column, 0, $pos + 1) . "%s" . substr($column, $pos);
                $temporaryFields[] = sprintf($fieldPattern, "day");
                $temporaryFields[] = sprintf($fieldPattern, "month");
                $temporaryFields[] = sprintf($fieldPattern, "year");
            }
        }

        $columns = $reportResult->getColumns();
        if (count($reports) > 0) {
            $unUsedColumns = array_diff_key($reports[0], $columns);
            foreach ($unUsedColumns as $field => $name) {
                $temporaryFields[] = $field;
            }
        }

        foreach ($temporaryFields as $temporaryField) {
            if (array_key_exists($temporaryField, $columns)) {
                unset($columns[$temporaryField]);
            }

            if (array_key_exists($temporaryField, $types)) {
                unset($types[$temporaryField]);
            }
        }
        $reportResult->setColumns($columns);
        $reportResult->setTypes($types);

        $reports = $reportResult->getReports();
        $reports = $this->getReportAfterRemoveTemporaryFields($reports, $temporaryFields);

        if (!empty($params->getUserDefinedDimensions())) {
            $newDimensions = $params->getUserDefinedDimensions();
            $newMetrics = $params->getUserDefinedMetrics();
            $newColumns = array_merge($newDimensions, $newMetrics);
            $fieldsToRemove = array_diff(array_keys($reportResult->getColumns()), $newColumns);
            $reports = $this->getReportAfterRemoveTemporaryFields($reports, $fieldsToRemove);
            $types = $reportResult->getTypes();
            foreach ($types as $k => $v) {
                if (in_array($k, $fieldsToRemove)) {
                    unset($types[$k]);
                }
            }

            $reportResult->setTypes($types);
            $oldColumns = $reportResult->getColumns();
            foreach ($oldColumns as $k => $v) {
                if (in_array($k, $newColumns)) {
                    continue;
                }

                unset($oldColumns[$k]);
            }
            $reportResult->setColumns($oldColumns);
            $totalFields = $reportResult->getTotal();
            foreach ($totalFields as $k => $v) {
                if (in_array($k, $newColumns)) {
                    continue;
                }

                unset($totalFields[$k]);
            }
            $reportResult->setTotal($totalFields);

            $averageFields = $reportResult->getAverage();
            foreach ($averageFields as $k => $v) {
                if (in_array($k, $newColumns)) {
                    continue;
                }

                unset($averageFields[$k]);
            }
            $reportResult->setAverage($averageFields);
        }

        $smartColumns = $this->getSmartColumns($reportResult->getColumns(), $params, $reportResult->getTypes());
        $reportResult->setColumns($smartColumns);

        $reports = $this->getReportAfterApplyDefaultFormat($reports, $reportResult->getColumns(), $reportResult->getTypes());
        $reportResult->setReports($reports);

        if (count($params->getSearches()) > 0) {
            $reports = $this->filterReports($reports, $params->getSearches(), $reportResult->getTypes());
        }

        if ($params->getSortField()) {
            $sortField = $params->getSortField();
            $sortField = str_replace('"', '', $sortField);
            $params->setSortField($sortField);

            if (!$params->getOrderBy()) {
                $params->setOrderBy('asc');
            }
            $reports = $this->sortReports($reports, $params->getSortField(), $params->getOrderBy(), $reportResult->getTypes());
        }

        $totalRow = count($reports);

        if (is_int($params->getPage())) {
            if (!is_int($params->getLimit()) || $params->getLimit() < 1) {
                $params->setLimit(10);
            }
            $offset = ($params->getPage() - 1) * $params->getLimit();
            $reports = array_splice($reports, $offset, $params->getLimit());
            $reportResult->setTotalPage(floor($totalRow / $params->getLimit()) + 1);
        }

        $reportResult->setReports($reports);
        $reportResult->setTotalReport($totalRow);


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
     * @param $outputJoinField
     */
    private function transformReports(Collection $reportCollection, array $transforms, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        /**
         * @var TransformInterface $transform
         */
        foreach ($transforms as $transform) {
            $transform->transform($reportCollection, $metrics, $dimensions, $outputJoinField);
        }
    }

    /**
     * filter Non-Selected Fields From Reports
     * All fields not selected from data set will be remove from report
     * All fields created from transformers (add field, ...) will be kept because that fields does not belong to data set
     *
     * @param ReportResultInterface $reportResult
     * @param array $nonSelectedFields selected fields that picked from data set.
     */
    private function filterNonSelectedFieldsFromReports(ReportResultInterface $reportResult, array $nonSelectedFields)
    {
        $reports = $reportResult->getReports();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        foreach ($nonSelectedFields as $nonSelectedField) {
            /* filter for all records of reports */
            foreach ($reports as &$row) {
                if (!is_array($row)) {
                    continue;
                }

                if (array_key_exists($nonSelectedField, $row)) {
                    unset($row[$nonSelectedField]);
                }
            }

            /* filter for totals */
            if (array_key_exists($nonSelectedField, $totals)) {
                unset($totals[$nonSelectedField]);
            }

            /* filter for averages */
            if (array_key_exists($nonSelectedField, $averages)) {
                unset($averages[$nonSelectedField]);
            }
        }

        /* set value again */
        $reportResult->setReports($reports);
        $reportResult->setTotal($totals);
        $reportResult->setAverage($averages);
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
        if (count($arrays) == 0) {
            $newElement[][$key] = $value;
            return $newElement;
        }

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

    /**
     * @param ReportViewDTO $reportViewDTO
     * @return array
     */
    protected function getDateTypeFieldsForReportView(ReportViewDTO $reportViewDTO)
    {
        /**@var ReportViewInterface $reportView */
        $reportView = $this->reportViewManager->find($reportViewDTO->getReportViewId());

        $fieldTypes = $reportView->getFieldTypes();
        $fields = [];
        foreach ($fieldTypes as $field => $type) {
            if ($type == self::TYPE_DATE || $type == self::TYPE_DATE_TIME) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    protected function getDateTypeFieldsOfDataSet($dataSetId)
    {
        /**@var DataSetInterface $dataSet */
        $dataSet = $this->dataSetManager->find($dataSetId);
        $allFields = array_merge($dataSet->getMetrics(), $dataSet->getDimensions());
        $fields = [];
        foreach ($allFields as $key => $type) {
            if ($type == self::TYPE_DATE || $type == self::TYPE_DATE_TIME) {
                $fields[] = $key;
            }
        }

        return $fields;
    }

    protected function createDateFilter($field, $startDate, $endDate)
    {
        $dateType = DateFilter::DATE_TYPE_CUSTOM_RANGE;
        $dateValue = array(DateFilter::DATE_VALUE_FILTER_START_DATE_KEY => $startDate, DateFilter::DATE_VALUE_FILTER_END_DATE_KEY => $endDate);
        $dateFormat = 'Y-m-d';

        $filter = new DateFilter();
        $filter->setFieldName($field);
        $filter->setDateType($dateType);
        $filter->setDateFormat($dateFormat);
        $filter->setDateValue($dateValue);

        return $filter;
    }

    /**
     * @param array $reports
     * @param array $searches
     * @param array $types
     * @return mixed
     */
    private function filterReports(array $reports, array $searches, array $types)
    {
        foreach ($searches as $searchField => $searchContent) {
            if (!array_key_exists($searchField, $types)) {
                continue;
            }
            $type = $types[$searchField];

            foreach ($reports as $pos => &$report) {
                if (!array_key_exists($searchField, $report)) {
                    continue;
                }
                $value = $report[$searchField];

                //Filter number
                if ($type == FieldType::NUMBER || $type == FieldType::DECIMAL) {
                    $conditions = preg_split('/[\s]+/', $searchContent);
                    $value = $type == FieldType::NUMBER ? intval($value) : floatval($value);
                    foreach ($conditions as $condition) {
                        if (!$this->compareMathCondition($condition, $value)) {
                            unset($reports[$pos]);
                            continue;
                        }
                    }
                    continue;
                }

                //Filter text, date...
                if ($type == FieldType::TEXT || $type == FieldType::LARGE_TEXT || $type == FieldType::DATE || $type == FieldType::DATETIME) {
                    $words = explode(" ", $searchContent);
                    foreach ($words as $word) {
                        $pattern = sprintf('/%s/i', strtolower($word));
                        if (!preg_match($pattern, strtolower($value))) {
                            unset($reports[$pos]);
                            continue;
                        }
                    }
                    continue;
                }
            }
        }

        return $reports;
    }

    /**
     * @param array $reports
     * @param $sortField
     * @param $orderBy
     * @param array $types
     * @return mixed
     */
    private function sortReports(array $reports, $sortField, $orderBy, array $types)
    {
        $reports = array_values($reports);
        if (count($reports) < 1) {
            return $reports;
        }

        if (!array_key_exists($sortField, $reports[0])) {
            return $reports;
        }

        if (!array_key_exists($sortField, $types)) {
            return $reports;
        }

        $type = $types[$sortField];

        usort($reports, function ($a, $b) use ($sortField, $orderBy, $type) {
            $firstValue = $a[$sortField];
            $secondValue = $b[$sortField];

            switch ($type) {
                case FieldType::NUMBER:
                    $firstValue = intval($firstValue);
                    $secondValue = intval($secondValue);
                    break;
                case FieldType::DECIMAL:
                    $firstValue = floatval($firstValue);
                    $secondValue = floatval($secondValue);
                    break;
                case FieldType::DATE:
                    $firstValue = \DateTime::createFromFormat("Y-m-d", $firstValue);
                    $secondValue = \DateTime::createFromFormat("Y-m-d", $secondValue);
                    break;
                case FieldType::DATETIME:
                    $firstValue = \DateTime::createFromFormat("Y-m-d H:i:s", $firstValue);
                    $secondValue = \DateTime::createFromFormat("Y-m-d H:i:s", $secondValue);
                    break;
            }

            return $orderBy == 'desc' ? $firstValue > $secondValue : $firstValue < $secondValue;
        });

        return $reports;
    }

    /**
     * @param $condition
     * @param $value
     * @return bool
     */
    private function compareMathCondition($condition, $value)
    {
        if (preg_match('/([^\d]+)([0-9\.]+)/', $condition, $matches)) {
            $compareOperator = $matches[1];
            $compareValue = $matches[2];

            switch ($compareOperator) {
                case '=':
                    return $value == $compareValue;
                case '==':
                    return $value == $compareValue;
                case '>':
                    return $value > $compareValue;
                case '>= ':
                    return $value >= $compareValue;
                case '<':
                    return $value < $compareValue;
                case '<=':
                    return $value <= $compareValue;
                case '!':
                    return $value != $compareValue;
                case '!=':
                    return $value != $compareValue;
            }
        };

        return true;
    }

    /**
     * @param $columns
     * @param ParamsInterface $params
     * @param $types
     * @return mixed
     */
    private function getSmartColumns($columns, $params, $types)
    {
        $dimensions = [];
        $metrics = [];

        /**
         * Get dimensions and metrics with suffix dataSet id,
         * Example text_1 mean in dataSet 1 have column text
         */
        $dataSets = $params->getDataSets();
        foreach ($dataSets as $dataSet) {
            $dimensions = array_merge($dimensions, array_map(function ($item) use ($dataSet) {
                /** @var DataSetInterface $dataSet */
                return sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }, $dataSet->getDimensions()));

            $metrics = array_merge($metrics, array_map(function ($item) use ($dataSet) {
                /** @var DataSetInterface $dataSet */
                return sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }, $dataSet->getMetrics()));
        }

        if ($params->getUserDefinedDimensions() != null) {
            $dimensions = array_unique(array_merge($dimensions, $params->getUserDefinedDimensions()));
        }

        if ($params->getUserDefinedMetrics() != null) {
            $metrics = array_unique(array_merge($metrics, $params->getUserDefinedMetrics()));
        }

        $calculatedFields = array_diff_key($columns, array_flip(array_merge($dimensions, $metrics)));

        $dateDimensions = array_filter($dimensions, function ($dimension) use ($types) {
            return array_key_exists($dimension, $types) && ($types[$dimension] == FieldType::DATE || $types[$dimension] == FieldType::DATETIME);
        });
        uasort($dateDimensions, array($this, 'compareFieldsWithOutDataSetId'));

        $alphabetDimensions = array_diff($dimensions, $dateDimensions);
        uasort($alphabetDimensions, array($this, 'compareFieldsWithOutDataSetId'));
        $alphabetDimensions = array_values($alphabetDimensions);

        $dateMetrics = array_filter($metrics, function ($metric) use ($types) {
            return array_key_exists($metric, $types) && ($types[$metric] == FieldType::DATE || $types[$metric] == FieldType::DATE);
        });
        $alphabetMetrics = array_diff($metrics, $dateMetrics);
        uasort($alphabetMetrics, array($this, 'compareFieldsWithOutDataSetId'));
        $alphabetMetrics = array_values($alphabetMetrics);

        /**
         * Order
         *      - dimensions first
         *          - date, datetime
         *          - sort the remaining alphabetically
         *      - metrics
         *          - date, datetime
         *          - sort the remaining alphabetically
         */
        $smartColumns = [];

        $reportViewAlias = 'report_view_alias';
        if (array_key_exists($reportViewAlias, $columns)) {
            $smartColumns[$reportViewAlias] = $columns[$reportViewAlias];
            if ($params->getUserDefinedDimensions() != null && array_key_exists($reportViewAlias, $params->getUserDefinedDimensions())) {
                unset($params->getUserDefinedDimensions()[$reportViewAlias]);
            }
            if ($params->getUserDefinedMetrics() != null && array_key_exists($reportViewAlias, $params->getUserDefinedMetrics())) {
                unset($params->getUserDefinedMetrics()[$reportViewAlias]);
            }
        }

        foreach ($dateDimensions as $dimension) {
            if (array_key_exists($dimension, $columns)) {
                $smartColumns[$dimension] = $columns[$dimension];
            }
        }

        foreach ($alphabetDimensions as $dimension) {
            if (array_key_exists($dimension, $columns)) {
                $smartColumns[$dimension] = $columns[$dimension];
            }
        }

        foreach ($dateMetrics as $metric) {
            if (array_key_exists($metric, $columns)) {
                $smartColumns[$metric] = $columns[$metric];
            }
        }

        foreach ($alphabetMetrics as $metric) {
            if (array_key_exists($metric, $columns)) {
                $smartColumns[$metric] = $columns[$metric];
            }
        }

        $smartColumns = array_merge($smartColumns, $calculatedFields);

        return $smartColumns;
    }

    /**
     * @param $reports
     * @param $columns
     * @param $types
     * @return mixed;
     */
    private function getReportAfterApplyDefaultFormat($reports, $columns, $types)
    {
        $decimalFields = [];
        foreach ($columns as $key => $column) {
            if (array_key_exists($key, $types) && $types[$key] == FieldType::DECIMAL) {
                $decimalFields[$key] = $column;
            }
        }
        foreach ($reports as &$report) {
            foreach ($decimalFields as $decimalField => $name) {
                if (!array_key_exists($decimalField, $report)) {
                    continue;
                }
                if (strpos($report[$decimalField], '$') != false) {
                    continue;
                }
                if (strpos($report[$decimalField], '%') != false) {
                    continue;
                }
                $report[$decimalField] = number_format((float)$report[$decimalField], 4, ".", "");
            }
        }
        return $reports;
    }

    /**
     * @param $reports
     * @param $removeColumns
     * @return mixed;
     */
    private function getReportAfterRemoveTemporaryFields($reports, $removeColumns)
    {
        foreach ($reports as &$report) {
            foreach ($removeColumns as $removeColumn) {
                if (!array_key_exists($removeColumn, $report)) {
                    continue;
                }
                unset($report[$removeColumn]);
            }
        }
        return $reports;
    }

    /**
     * @param $first
     * @param $second
     * @return int
     */
    private function compareFieldsWithOutDataSetId($first, $second)
    {
        $firstField = null;
        $firstDataSet = null;
        $secondField = null;
        $secondDataSet = null;

        if (preg_match('/([a-zA-Z0-9 ]*)(_)([0-9]*)/', $first, $matches)) {
            $firstField = $matches[1];
            $firstDataSet = $matches[3];
        }

        if (preg_match('/([a-zA-Z0-9 ]*)(_)([0-9]*)/', $second, $matches)) {
            $secondField = $matches[1];
            $secondDataSet = $matches[3];
        }

        if ($firstField == null || $secondField == null) {
            return $this->compare($first, $second);
        }

        if ($this->compare($firstField, $secondField) != 0) {
            return $this->compare($firstField, $secondField);
        }

        return $this->compare($firstDataSet, $secondDataSet);
    }

    /**
     * @param $first
     * @param $second
     * @return int
     */
    private function compare($first, $second)
    {
        if ($first == $second) {
            return 0;
        }
        return ($first < $second) ? -1 : 1;
    }
}