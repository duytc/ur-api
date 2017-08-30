<?php

namespace UR\Service\Report;

use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use SplDoublyLinkedList;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\DataSets\DataSetInterface as DataSetDTO;
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
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Domain\DTO\Report\Transforms\ReplaceTextTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\StringUtilTrait;

class ReportBuilder implements ReportBuilderInterface
{
    use StringUtilTrait;
    use ColumnUtilTrait;

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
     * @var ReportViewFilter
     */
    private $reportViewFilter;

    /***
     * @var ReportViewFormatter
     */
    private $reportViewFormatter;

    /** @var ReportViewSorter  */
    private $reportViewSorter;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * ReportBuilder constructor.
     * @param ReportSelectorInterface $reportSelector
     * @param ReportGrouperInterface $reportGrouper
     * @param ReportViewManagerInterface $reportViewManager
     * @param ParamsBuilderInterface $paramsBuilder
     * @param DataSetManagerInterface $dataSetManager
     * @param ReportViewFilter $reportViewFilter
     * @param ReportViewFormatter $reportViewFormatter
     * @param ReportViewSorter $reportViewSorter
     * @param LoggerInterface $logger
     */
    public function __construct(ReportSelectorInterface $reportSelector, ReportGrouperInterface $reportGrouper,
            ReportViewManagerInterface $reportViewManager, ParamsBuilderInterface $paramsBuilder, DataSetManagerInterface $dataSetManager,
            ReportViewFilter $reportViewFilter, ReportViewFormatter $reportViewFormatter, ReportViewSorter $reportViewSorter, LoggerInterface $logger)
    {
        $this->reportSelector = $reportSelector;
        $this->reportGrouper = $reportGrouper;
        $this->reportViewManager = $reportViewManager;
        $this->paramsBuilder = $paramsBuilder;
        $this->dataSetManager = $dataSetManager;
        $this->reportViewFilter = $reportViewFilter;
        $this->reportViewFormatter = $reportViewFormatter;
        $this->reportViewSorter = $reportViewSorter;
        $this->logger = $logger;
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
        $rows = $reportResult->getRows();
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

        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            $report = $this->filterFieldsInArray($fieldsToBeShared, $row);
            $newRows->push($report);
            unset($row);
        }

        unset($rows, $row);
        $reportResult->setRows($newRows);

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
        $rows = new SplDoublyLinkedList();
        $dimensions = [];
        $metrics = [];
        $types = [];
        $dateRanges = [];

        $reportViews = $params->getReportViews();
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
            if (is_array($params->getUserDefinedMetrics())) {
                $reportParam->setShowInTotal(array_merge($params->getUserDefinedMetrics(), $reportView->getMetrics()));
            } else {
                $reportParam->setShowInTotal($reportView->getMetrics());
            }

            $viewFilters = $reportView->getFilters();

            if ($reportParam->getStartDate() !== null || $reportParam->getEndDate() !== null) {
                $reportParam->setStartDate($startDate);
                $reportParam->setEndDate($endDate);
            }

            $result = $this->getSingleReport($reportParam, $viewFilters, $isNeedFormatReport = false); // do not format report to avoid error when doing duplicate format
            foreach ($result->getTypes() as $field => $type) {
                if (in_array($type, [FieldType::NUMBER, FieldType::DECIMAL])) {
                    $types[$field] = $type;
                }
            }

            $total = $result->getTotal();
            if (!empty($total)) {
                $total = array_merge($total, array(self::REPORT_VIEW_ALIAS => $view->getAlias()));
                $rows->push($total);
            }
            $metrics = array_unique(array_merge($metrics, array_keys($total)));

            $dateRange = $result->getDateRange();
            if (!$dateRange instanceof DateRange) {
                continue;
            }
            $dateRanges[] = $dateRange;
        }

        $dimensions[] = self::REPORT_VIEW_ALIAS;
        $types[self::REPORT_VIEW_ALIAS] = FieldType::TEXT;

        if ($rows->count() == 0) {
            return new ReportResult([], [], [], []);
        }

        $collection = new Collection(array_unique(array_merge($metrics, $dimensions)), $rows, $types);

        /* get final reports */
        return $this->finalizeMultipleReport($collection, $params, $metrics, $dimensions, $dateRanges);
    }

    /**
     * @param Collection $reportCollection
     * @param ParamsInterface $params
     * @param array $metrics
     * @param array $dimensions
     * @param $dateRanges
     * @param array $outputJoinField
     * @param bool $isNeedFormatReport
     * @param array $overridingFilters
     * @return ReportResultInterface
     */
    private function finalizeMultipleReport(Collection $reportCollection, ParamsInterface $params, array $metrics, array $dimensions,
            $dateRanges, array $outputJoinField = [], $isNeedFormatReport = true, $overridingFilters = [])
    {
        /* transform data */
        $userProvidedDimensions = [];
        $userProvidedMetrics = [];
        $transforms = is_array($params->getTransforms()) ? $params->getTransforms() : [];
        if (!empty($params->getUserDefinedDimensions()) && $params->getCustomDimensionEnabled()) {
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

            $userProvidedDimensions = $params->getUserDefinedDimensions();
            $userProvidedMetrics = $params->getUserDefinedMetrics();
            $dimensions = array_values(array_unique(array_merge($dimensions, $userProvidedDimensions)));
            $metrics = array_values(array_unique(array_merge($metrics, $userProvidedMetrics)));
        }

        if (count($transforms) > 0) {
            $this->transformReports($reportCollection, $transforms, $metrics, $dimensions, $outputJoinField);
        }

        /* build columns that will be showed in total */
        $showInTotal = is_array($params->getShowInTotal()) ? $params->getShowInTotal() : $metrics;

        /* group reports */
        /** @var ReportResultInterface $reportResult */
        $reportResult = $this->reportGrouper->groupForMultiView($reportCollection, $showInTotal, $params->getWeightedCalculations(), $dateRanges, $params->getIsShowDataSetName());

        /* Filter all non-selected fields from reports data */
        $nonSelectedFields = [];

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

            $selectedFields = array_unique(array_merge($selectedFields, $userProvidedDimensions, $userProvidedMetrics));
            $nonSelectedFields = array_diff($allSubReportFields, $selectedFields);
        }

        // do filter
        $this->filterNonSelectedFieldsFromReports($reportResult, $nonSelectedFields);

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
        $newFieldsTransform = [];
        if (count($transforms) > 0) {
            foreach ($transforms as $transform) {
                if ($transform instanceof NewFieldTransform) {
                    $columns[$transform->getFieldName()] = $transform->getFieldName();
                    $newFieldsTransform[] = $transform->getFieldName();
                }
            }
        }
        $reports = $reportCollection->getRows();
        if ($reports->count() > 0) {
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

        $reportResult = $this->getReportAfterRemoveTemporaryFields($reportResult, $temporaryFields);

        if (!empty($params->getUserDefinedDimensions()) && $params->getCustomDimensionEnabled()) {
            $newDimensions = $params->getUserDefinedDimensions();
            $newMetrics = $params->getUserDefinedMetrics();

            $newColumns = array_merge($newDimensions, $newMetrics);
            $fieldsToRemove = array_diff(array_keys($reportResult->getColumns()), $newColumns);
            $reportResult = $this->getReportAfterRemoveTemporaryFields($reportResult, $fieldsToRemove);

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

        $reportResult = $this->reportViewFormatter->getSmartColumns($reportResult, $params, $newFieldsTransform);
        $reportResult = $this->reportViewFormatter->getReportAfterApplyDefaultFormat($reportResult, $params);

        if (count($params->getSearches()) > 0) {
            $reportResult = $this->reportViewFilter->filterReports($reportResult, $params);
        }

        if ($params->getSortField()) {
            $sortField = $params->getSortField();
            $sortField = str_replace('"', '', $sortField);
            $params->setSortField($sortField);

            if (!$params->getOrderBy()) {
                $params->setOrderBy('asc');
            }
            $reportResult = $this->reportViewSorter->sortReports($reportResult, $params);
        }

        $totalRow = $reportResult->getRows()->count();
        $reportResult->setTotalReport($totalRow);

        $rows = iterator_to_array($reportResult->getRows());
        if (is_int($params->getPage())) {
            if (!is_int($params->getLimit()) || $params->getLimit() < 1) {
                $params->setLimit(10);
            }
            $offset = ($params->getPage() - 1) * $params->getLimit();

            $rows = array_splice($rows, $offset, $params->getLimit());
            $reportResult->setTotalPage(floor($totalRow / $params->getLimit()) + 1);
            $newRows = new SplDoublyLinkedList();
            foreach ($rows as $row) {
                $newRows->push($row);
            }

            $reportResult->setRows($newRows);
        }


        /* format data if need */
        if ($isNeedFormatReport) {
            /** @var FormatInterface[] $formats */
            $formats = is_array($params->getFormats()) ? $params->getFormats() : [];
            $this->reportViewFormatter->formatReports($reportResult, $formats, $metrics, $dimensions);
        }

        $rows = $reportResult->getRows();
        $columns = $reportResult->getColumns();
        foreach ($rows as $index => $row) {
            $diff = array_diff_key($columns, $row);
            if (count($diff) > 0) {
                foreach ($diff as $field => $type) {
                    $row[$field] = null;
                }
                $rows->offsetSet($index, $row);
            }
        }
        /* return report result */
        return $reportResult;
    }
    /**
     * @param ReportViewDTO $reportView
     * @param $startDate
     * @param $endDate
     * @return ReportViewDTO
     */
    private function overrideDateTypeFiltersForReportView(ReportViewDTO $reportView, $startDate, $endDate)
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

    /**
     * @param ParamsInterface $params
     * @param null $overridingFilters
     * @param bool $isNeedFormatReport
     * @return mixed|ReportResult|ReportResultInterface
     */
    private function getSingleReport(ParamsInterface $params, $overridingFilters = null, $isNeedFormatReport = true)
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

            if ($config->isMultiple()) {
                $outputs = explode(',', $config->getOutputField());
                foreach (explode(',', $config->isVisible()) as $i => $v) {
                    if (filter_var($v, FILTER_VALIDATE_BOOLEAN)) {
                        $outputJoinFields[] = $outputs[$i];
                    }
                }
            } elseif ($config->isVisible()) {
                $outputJoinFields[] = $config->getOutputField();
            }
        }

        $outputJoinFields = array_unique($outputJoinFields);

        if ($startDate instanceof \DateTime && $endDate instanceof \DateTime) {
            foreach ($dataSets as $dataSet) {
                $this->overrideDateTypeFilterForDataSet($dataSet, $startDate, $endDate);
            }
        }

        /* get all metrics and dimensions from dataSets */
        /** @var DataSet $dataSet */
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $itemWithDataSetId = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                if (in_array($itemWithDataSetId, $flattenJoinFields)) {
                    continue;
                }

                $metrics[] = $itemWithDataSetId;
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
        $transforms = is_array($params->getTransforms()) ? $params->getTransforms() : [];
        if (!empty($params->getUserDefinedDimensions()) && $params->getCustomDimensionEnabled()) {
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

            foreach ($joinConfig as &$config) {
                if (in_array($config->getOutputField(), $params->getUserDefinedDimensions())) {
                    $config->setVisible(true);
                }

                if (in_array($config->getOutputField(), $params->getUserDefinedMetrics())) {
                    $config->setVisible(true);
                }
            }

            unset($config);
            $params->setJoinConfigs($joinConfig);
            $metrics = array_merge($metrics, $params->getUserDefinedMetrics());
            $metrics = array_unique($metrics);
            $dimensions = array_merge($metrics, $params->getUserDefinedDimensions());
            $dimensions = array_unique($dimensions);
        }

        $params->setTransforms($transforms);
        $data = $this->reportSelector->getReportData($params, $overridingFilters);
        /** @var Statement $stmt */
        $stmt = $data[SqlBuilder::STATEMENT_KEY];
        /** @var QueryBuilder $stmt */
        $subQuery = $data[SqlBuilder::SUB_QUERY];
        $rows = new SplDoublyLinkedList();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rows->push($row);
        }

        if (count($rows) < 1) {
            $columns = array_merge($metrics, $dimensions);
            $headers = [];
            foreach ($columns as $index => $column) {
                $headers[$column] = $this->convertColumn($column, $params->getIsShowDataSetName());
            }
            return new ReportResult(new SplDoublyLinkedList(), [], [], null, $headers, $types, 0);
        }

        $collection = new Collection(array_merge($metrics, $dimensions), $rows, $types);

        return $this->finalizeSingleReport($subQuery, $collection, $params, $metrics, $dimensions, $outputJoinFields, $isNeedFormatReport, $overridingFilters);
    }

    /**
     * @param $subQuery
     * @param Collection $reportCollection
     * @param ParamsInterface $params
     * @param array $metrics
     * @param array $dimensions
     * @param array $outputJoinField
     * @param bool $isNeedFormatReport
     * @param array $overridingFilters
     * @return mixed|ReportResultInterface
     */
    private function finalizeSingleReport($subQuery, Collection $reportCollection, ParamsInterface $params, array $metrics,
        array $dimensions, array $outputJoinField = [], $isNeedFormatReport = true, $overridingFilters = [])
    {
        $userProvidedDimensions = [];
        $userProvidedMetrics = [];
        $transforms = is_array($params->getTransforms()) ? $params->getTransforms() : [];
        if (!empty($params->getUserDefinedDimensions()) && $params->getCustomDimensionEnabled()) {
            $userProvidedDimensions = $params->getUserDefinedDimensions();
            $userProvidedMetrics = $params->getUserDefinedMetrics();
            $dimensions = array_values(array_unique(array_merge($dimensions, $userProvidedDimensions)));
            $metrics = array_values(array_unique(array_merge($metrics, $userProvidedMetrics)));
        }


        foreach ($transforms as $transform) {
            if ($transform instanceof ReplaceTextTransform) {
                $transform->transform($reportCollection, $metrics, $dimensions, $outputJoinField);
            }
        }

        /** @var JoinConfigInterface[] $joinConfig */
        $joinConfig = $params->getJoinConfigs();
        $hiddenJoinFields = [];
        foreach ($joinConfig as $config) {
            if ($config->isMultiple()) {
                $outputs = explode(',', $config->getOutputField());
                foreach (explode(',', $config->isVisible()) as $i => $v) {
                    if (filter_var($v, FILTER_VALIDATE_BOOLEAN)) {
                        $columns = $reportCollection->getColumns();
                        $columns[] = $outputs[$i];
                        $reportCollection->setColumns($columns);
                        continue;
                    }

                    if (in_array($outputs[$i], $dimensions) || in_array($outputs[$i], $metrics)) {
                        continue;
                    }

                    $hiddenJoinFields[] = $outputs[$i];
                }

                continue;
            }


            if (!$config->isVisible() && !in_array($config->getOutputField(), $metrics) && !in_array($config->getOutputField(), $dimensions)) {
                $hiddenJoinFields[] = $config->getOutputField();
            } else {
                $columns = $reportCollection->getColumns();
                $columns[] = $config->getOutputField();
                $reportCollection->setColumns($columns);
            }
        }

        $reports = $reportCollection->getRows();
        if (!empty($hiddenJoinFields)) {
            gc_enable();
            $newReports = new SplDoublyLinkedList();
            $hiddenJoinFields = array_flip($hiddenJoinFields);
            $reports->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
            foreach ($reports as $report) {
                $report = array_diff_key($report, $hiddenJoinFields);
                $newReports->push($report);
                unset($report);
            }

            unset($reports, $report);
            gc_collect_cycles();
            $reportCollection->setRows($newReports);
        }

        $reportResult = $this->reportGrouper->groupForSingleView($subQuery, $reportCollection, $params, $overridingFilters);

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

                $allDataSetFieldsTmp = array_keys(array_merge($dataSetEntity->getDimensions(), $dataSetEntity->getMetrics()));

                // merge hidden fields from data set DTO to data set entity
                $allDataSetFieldsTmp = array_merge($allDataSetFieldsTmp, $dataSet->getDimensions(), $dataSet->getMetrics());
                $allDataSetFieldsTmp = array_values(array_unique($allDataSetFieldsTmp));

                // get all selected fields from data set DTO
                $selectedFieldsTmp = array_merge($metrics, $dimensions);

                // convert field to field_<data set id>
                $allDataSetFieldsTmp = array_map(function ($item) use ($dataSet) {
                    return sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }, $allDataSetFieldsTmp);

                $allDataSetFields = array_merge($allDataSetFields, $allDataSetFieldsTmp);
                $selectedFields = array_merge($selectedFields, $selectedFieldsTmp);
            }

            $selectedFields = array_unique(array_merge($selectedFields, $userProvidedDimensions, $userProvidedMetrics));
            $nonSelectedFields = array_diff($allDataSetFields, $selectedFields);
        }

        // do filter
        $this->filterNonSelectedFieldsFromReports($reportResult, $nonSelectedFields);

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

        // add new fields to column list
        $columns = $reportResult->getColumns();
        if (count($transforms) > 0) {
            foreach ($transforms as $transform) {
                if ($transform instanceof NewFieldTransform) {
                    $columns[$transform->getFieldName()] = $transform->getFieldName();
                }
            }
        }

        $reports = $reportCollection->getRows();
        if ($reports->count() > 0) {
            $unUsedColumns = array_diff_key($reports[0], $columns);
            foreach ($unUsedColumns as $field => $name) {
                $temporaryFields[] = $field;
            }
        }

        foreach ($temporaryFields as $temporaryField) {
            unset($columns[$temporaryField]);
            unset($types[$temporaryField]);
        }

        $reportResult->setColumns($columns);
        $reportResult->setTypes($types);


        $reportResult = $this->getReportAfterRemoveTemporaryFields($reportResult, $temporaryFields);

        if (!empty($params->getUserDefinedDimensions()) && $params->getCustomDimensionEnabled()) {
            $newColumns = array_merge($params->getUserDefinedDimensions(), $params->getUserDefinedMetrics());
            $fieldsToRemove = array_diff(array_keys($reportResult->getColumns()), $newColumns);
            $reportResult = $this->getReportAfterRemoveTemporaryFields($reportResult, $fieldsToRemove);

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

        $reportResult = $this->reportViewFormatter->getSmartColumns($reportResult, $params);
        $reportResult = $this->reportViewFormatter->getReportAfterApplyDefaultFormat($reportResult, $params);

        /* format data if need */
        $formats = is_array($params->getFormats()) ? $params->getFormats() : [];
        if ($isNeedFormatReport && !empty($formats)) {
            /** @var FormatInterface[] $formats */
            $this->reportViewFormatter->formatReports($reportResult, $formats, $metrics, $dimensions);
        }

        /* return report result */
        return $reportResult;
    }

    /**
     * @param DataSetDTO $dataSet
     * @param $startDate
     * @param $endDate
     * @return DataSetDTO
     */
    private function overrideDateTypeFilterForDataSet(DataSetDTO &$dataSet, \DateTime $startDate, \DateTime $endDate)
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
        if (count($nonSelectedFields) < 1) {
            return;
        }

        $nonSelectedFields = array_flip($nonSelectedFields);

        $rows = $reportResult->getRows();
        $totals = $reportResult->getTotal();
        $averages = $reportResult->getAverage();

        gc_enable();
        $newRows = new SplDoublyLinkedList();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row = array_diff_key($row, $nonSelectedFields);
            $newRows->push($row);
            unset ($row);
        }

        $totals = array_diff_key($totals, $nonSelectedFields);
        $averages = array_diff_key($averages, $nonSelectedFields);

        unset($rows, $row);
        gc_collect_cycles();
        /* set value again */
        $reportResult->setRows($newRows);
        $reportResult->setTotal($totals);
        $reportResult->setAverage($averages);
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
     * @param ReportResultInterface $reportResult
     * @param $removeColumns
     * @return mixed;
     */
    private function getReportAfterRemoveTemporaryFields($reportResult, $removeColumns)
    {
        if (empty($removeColumns)) {
            return $reportResult;
        }

        $removeColumns = array_flip($removeColumns);
        $rows = $reportResult->getRows();
        $newRows = new SplDoublyLinkedList();

        gc_enable();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            $row = array_diff_key($row, $removeColumns);
            $newRows->push($row);
            unset($row);
        }

        unset($rows);
        gc_collect_cycles();
        $reportResult->setRows($newRows);
        return $reportResult;
    }

    /**
     * @param ParamsInterface $param
     * @return mixed
     */
    private function getFieldsFromTransforms($param)
    {
        $transforms = $param->getTransforms();

        $transformFields = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof NewFieldTransform) {
                $transformFields[] = $transform->getFieldName();
                continue;
            }
        }
        return $transformFields;
    }

    protected function getDataSetManager()
    {
        return $this->dataSetManager;
    }
}