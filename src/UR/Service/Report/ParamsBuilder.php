<?php

namespace UR\Service\Report;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Behaviors\JoinConfigUtilTrait;
use UR\Behaviors\ReportViewUtilTrait;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Formats\ColumnPositionFormat;
use UR\Domain\DTO\Report\Formats\CurrencyFormat;
use UR\Domain\DTO\Report\Formats\DateFormat;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\Formats\NumberFormat;
use UR\Domain\DTO\Report\Formats\PercentageFormat;
use UR\Domain\DTO\Report\JoinBy\JoinConfig;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\JoinBy\JoinField;
use UR\Domain\DTO\Report\JoinBy\JoinFieldInterface;
use UR\Domain\DTO\Report\Params;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportViews\ReportView;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddConditionValueTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\ReplaceTextTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Report\WeightedCalculation;

class ParamsBuilder implements ParamsBuilderInterface
{
    use JoinConfigUtilTrait;
    use ReportViewUtilTrait;

    const DATA_SET_KEY = 'reportViewDataSets';
    const FIELD_TYPES_KEY = 'fieldTypes';
    const TRANSFORM_KEY = 'transforms';
    const JOIN_BY_KEY = 'joinBy';
    const WEIGHTED_CALCULATION_KEY = 'weightedCalculations';
    const SUBVIEW_KEY = 'subView';
    const FILTERS_KEY = 'filters';
    const FORMAT_KEY = 'formats';
    const SHOW_IN_TOTAL_KEY = 'showInTotal';
    const SUB_REPORT_INCLUDED_KEY = 'subReportsIncluded';
    const START_DATE = 'startDate';
    const END_DATE = 'endDate';
    const USER_DEFINED_DATE_RANGE = 'userDefineDateRange';
    const USER_DEFINED_DIMENSIONS = 'userDefineDimensions';
    const USER_DEFINED_METRICS = 'userDefineMetrics';
    const IS_SHOW_DATA_SET_NAME = 'isShowDataSetName';
    const USER_PROVIDED_DIMENSION_ENABLED = 'enableCustomDimensionMetric';
    const REPORT_VIEW_ID = 'id';
    const PAGE_KEY = 'page';
    const LIMIT_KEY = 'limit';
    const SEARCHES = 'searches';
    const ORDER_BY_KEY = 'orderBy';
    const SORT_FIELD_KEY = 'sortField';
    const DIMENSIONS_KEY = 'dimensions';
    const METRICS_KEY = 'metrics';
    const METRIC_CALCULATIONS = 'metricCalculations';

    /**
     * @inheritdoc
     */
    public function buildFromArray(array $data)
    {
        $param = new Params();

        $subView = false;
        if (array_key_exists(self::SUBVIEW_KEY, $data)) {
            $subView = filter_var($data[self::SUBVIEW_KEY], FILTER_VALIDATE_BOOLEAN);
        }

        $param->setSubView($subView);

        if (array_key_exists(self::DIMENSIONS_KEY, $data)) {
            $param->setDimensions($data[self::DIMENSIONS_KEY]);
        }

        if (array_key_exists(self::METRICS_KEY, $data)) {
            $param->setMetrics($data[self::METRICS_KEY]);
        }

        /*
         * VERY IMPORTANT:
         * report param:
         *      dataSets => required for multiView=false
         *      fieldTypes,
         *      joinBy => required for multiView=false
         *      transforms,
         *      weightedCalculations,
         *      filters,
         *      reportView => required for subView=true
         *      showInTotal,
         *      formats,
         *      subReportsIncluded => required for multiView=true
         */
        if ($param->isSubView()) {
            if (array_key_exists(self::FILTERS_KEY, $data) && !empty($data[self::FILTERS_KEY])) {
                $param->setFilters(DataSet::createFilterObjects($data[self::FILTERS_KEY], $overrideFilter = true));
            }
        }

        if (array_key_exists(self::DATA_SET_KEY, $data) && !empty($data[self::DATA_SET_KEY])) {
//            if (array_key_exists(self::FILTERS_KEY, $data)) {
//                $dataSets = $this->createDataSets($data[self::DATA_SET_KEY], $data[self::FILTERS_KEY]);
//            } else {
            $dataSets = $this->createDataSets($data[self::DATA_SET_KEY], null);
//            }
            $param->setDataSets($dataSets);
        }

        if (array_key_exists(self::JOIN_BY_KEY, $data)) {
            $joinBy = $data[self::JOIN_BY_KEY];
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON format in JoinConfigs');
            }
            if (is_array($joinBy) && !empty($joinBy)) {
                $param->setJoinConfigs($this->createJoinConfigs($joinBy, $param->getDataSets()));
            }
        }

        if (array_key_exists(self::TRANSFORM_KEY, $data) && !empty($data[self::TRANSFORM_KEY])) {
            $transforms = $this->createTransforms($data[self::TRANSFORM_KEY]);
            $param->setTransforms($transforms);
        }

        if (array_key_exists(self::WEIGHTED_CALCULATION_KEY, $data)) {
            $calculations = $data[self::WEIGHTED_CALCULATION_KEY];
            if (!empty($calculations)) {
                $param->setWeightedCalculations(new WeightedCalculation($calculations));
            }
        }

        if (array_key_exists(self::SHOW_IN_TOTAL_KEY, $data)) {
            $param->setShowInTotal($data[self::SHOW_IN_TOTAL_KEY]);
        }

        if (array_key_exists(self::FIELD_TYPES_KEY, $data)) {
            $param->setFieldTypes($data[self::FIELD_TYPES_KEY]);
        }

        if (array_key_exists(self::FORMAT_KEY, $data) && !empty($data[self::FORMAT_KEY])) {
            $formats = $this->createFormats($data[self::FORMAT_KEY]);
            $param->setFormats($formats);
        }

        if (array_key_exists(self::IS_SHOW_DATA_SET_NAME, $data) && !empty($data[self::IS_SHOW_DATA_SET_NAME])) {
            $param->setIsShowDataSetName(filter_var($data[self::IS_SHOW_DATA_SET_NAME], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists(self::USER_PROVIDED_DIMENSION_ENABLED, $data) && !empty($data[self::USER_PROVIDED_DIMENSION_ENABLED])) {
            $param->setUserProvidedDimensionEnabled(filter_var($data[self::USER_PROVIDED_DIMENSION_ENABLED], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists(self::USER_PROVIDED_DIMENSION_ENABLED, $data) && $data[self::USER_PROVIDED_DIMENSION_ENABLED] == true) {
            if (array_key_exists(self::USER_DEFINED_DIMENSIONS, $data)) {
                $param->setUserDefinedDimensions($data[self::USER_DEFINED_DIMENSIONS]);
            }
            if (array_key_exists(self::USER_DEFINED_METRICS, $data)) {
                $param->setUserDefinedMetrics($data[self::USER_DEFINED_METRICS]);
            }
            $transforms = $param->getTransforms();
            foreach ($transforms as &$transform) {
                if (!$transform instanceof GroupByTransform) {
                    continue;
                }
                $transform->setFields($param->getUserDefinedDimensions());
            }
            $param->setTransforms($transforms);
        }

        if (array_key_exists(self::START_DATE, $data) && !empty($data[self::START_DATE])) {
            $param->setStartDate(new \DateTime($data[self::START_DATE]));
        }

        if (array_key_exists(self::END_DATE, $data) && !empty($data[self::END_DATE])) {
            $param->setEndDate(new \DateTime($data[self::END_DATE]));
        }

        if (array_key_exists(self::REPORT_VIEW_ID, $data) && !empty($data[self::REPORT_VIEW_ID])) {
            $param->setReportViewId($data[self::REPORT_VIEW_ID]);
        }

        if (array_key_exists(self::ORDER_BY_KEY, $data)) {
            $param->setOrderBy($data[self::ORDER_BY_KEY]);
        }

        if (array_key_exists(self::SORT_FIELD_KEY, $data)) {
            $param->setSortField($data[self::SORT_FIELD_KEY]);
        }

        if (array_key_exists(self::PAGE_KEY, $data)) {
            $param->setPage(intval($data[self::PAGE_KEY]));
        }
        if (array_key_exists(self::LIMIT_KEY, $data)) {
            $param->setLimit(intval($data[self::LIMIT_KEY]));
        }

        if (array_key_exists(self::SEARCHES, $data)) {
            $searches = $data[self::SEARCHES];
            if (is_string($searches)) {
                $searches = json_decode($searches, true);
            }
            $param->setSearches($searches);
        }

        return $param;
    }

    /**
     * @param array $dataSets
     * @param array $filters
     * @return array
     */
    protected function createDataSets(array $dataSets, array $filters = null)
    {
        $dataSetObjects = [];
        foreach ($dataSets as $dataSet) {
            if (!is_array($dataSet)) {
                throw new InvalidArgumentException(sprintf('expect array, got %s', gettype($dataSet)));
            }

            if ($filters) {
                foreach ($filters as $filter) {
                    if ($dataSet['dataSet'] == $filter['dataSet']) {
                        $dataSet['filters'][] = $filter;
                    }
                }
            }

            $dataSetObjects[] = new DataSet($dataSet);
        }

        return $dataSetObjects;
    }

    /**
     * create JoinConfig objects from json data
     * @param array $data
     * @param array $dataSets
     * @return array
     */
    protected function createJoinConfigs(array $data, array $dataSets)
    {
        $joinedDataSets = [];
        $joinConfigs = [];
        $this->normalizeJoinConfig($data, $dataSets);

        foreach ($data as $config) {
            $joinConfig = new JoinConfig();
            $joinConfig->setOutputField($config[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD]);
            $joinConfig->setVisible(array_key_exists(SqlBuilder::JOIN_CONFIG_VISIBLE, $config) ? $config[SqlBuilder::JOIN_CONFIG_VISIBLE] : true);
            $joinConfig->setMultiple(array_key_exists(SqlBuilder::JOIN_CONFIG_MULTIPLE, $config) ? $config[SqlBuilder::JOIN_CONFIG_VISIBLE] : false);

            $joinFields = $config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            foreach ($joinFields as $joinField) {
                $joinConfig->addJoinField(new JoinField($joinField[SqlBuilder::JOIN_CONFIG_DATA_SET], $joinField[SqlBuilder::JOIN_CONFIG_FIELD]));
                $joinedDataSets[] = $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
            }

            $joinConfig->setDataSets();
            $joinConfigs[] = $joinConfig;
            unset($joinConfig);
        }

        $startDataSets = [];
        $endDataSets = [];
        $dataSetIds = array_map(function (DataSet $dataSet) {
            return $dataSet->getDataSetId();
        }, $dataSets);
        $startDataSet = current($dataSetIds);

        while (count($startDataSets) <= count($dataSetIds)) {
            if (!in_array($startDataSet, $startDataSets)) {
                $startDataSets[] = $startDataSet;
            }

            $endNodes = $this->findEndNodesForDataSet($joinConfigs, $startDataSet, $startDataSets);
            if (empty($endNodes)) {
                $startDataSet = array_shift($endDataSets);
                if ($startDataSet === null) {
                    if (count($startDataSets) < count($dataSetIds) - 1) {
                        throw new InvalidArgumentException("There's seem to be some data set is missing from the join config");
                    }
                    break;
                }
                continue;
            }

            $endDataSets = $this->getListEndDataSets($endNodes, $startDataSet);
            $startDataSet = array_shift($endDataSets);
            if ($startDataSet === null) {
                if (count($startDataSets) < count($dataSetIds) - 1) {
                    throw new InvalidArgumentException("There's seem to be some data set is missing from the join config");
                }
                break;
            }
        }

        return $joinConfigs;
    }

    private function getToDataSet($joinConfig, $fromDataSetId)
    {
        /** @var JoinFieldInterface $config */
        foreach ($joinConfig as $config) {
            $dataSetId = $config->getDataSet();
            if ($fromDataSetId == $dataSetId) {
                continue;
            }

            return $dataSetId;
        }

        return $fromDataSetId;
    }

    private function getListEndDataSets($joinConfig, $fromDataSetId)
    {
        $endDataSets = [];
        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            $endDataSets[] = $this->getToDataSet($config->getJoinFields(), $fromDataSetId);
        }

        return $endDataSets;
    }

    /**
     * @param array $reportViews
     * @return array
     */
    public static function createReportViews(array $reportViews)
    {
        $reportViewObjects = [];
        foreach ($reportViews as $reportView) {
            if (!is_array($reportView)) {
                throw new InvalidArgumentException(sprintf('expect array, got %s', gettype($reportView)));
            }

            $reportViewObjects[] = new ReportView($reportView);
        }

        return $reportViewObjects;
    }

    public function reportViewDataSetObjectsToArray($reportViewDataSets)
    {
        if ($reportViewDataSets instanceof PersistentCollection) {
            $reportViewDataSets = $reportViewDataSets->toArray();
        }

        $reportViewData = [];
        /**
         * @var ReportViewDataSetInterface $reportViewDataSet
         */
        foreach ($reportViewDataSets as $reportViewDataSet) {
            if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                throw new InvalidArgumentException(sprintf('expect ReportViewDataSetInterface, got %s', get_class($reportViewDataSet)));
            }

            $reportViewData[] = array(
                DataSet::DATA_SET_ID_KEY => $reportViewDataSet->getDataSet()->getId(),
                DataSet::DIMENSIONS_KEY => $reportViewDataSet->getDimensions(),
                DataSet::METRICS_KEY => $reportViewDataSet->getMetrics(),
                DataSet::FILTERS_KEY => $reportViewDataSet->getFilters(),
            );
        }

        return $reportViewData;
    }

    /**
     * @param array $transforms
     * @return array
     */
    public function createTransforms(array $transforms)
    {
        $transformObjects = [];
        foreach ($transforms as $transform) {
            if (!array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform)) {
                throw new InvalidArgumentException('"transformType" is missing');
            }

            switch ($transform[TransformInterface::TRANSFORM_TYPE_KEY]) {
                case TransformInterface::ADD_FIELD_TRANSFORM:
                    $isPostGroup = array_key_exists(TransformInterface::TRANSFORM_IS_POST_KEY, $transform) ? $transform[TransformInterface::TRANSFORM_IS_POST_KEY] : true;
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $addField) {
                        $transformObjects[] = new AddFieldTransform($addField, $isPostGroup);
                    }
                    break;

                case TransformInterface::ADD_CALCULATED_FIELD_TRANSFORM:
                    $expressionLanguage = new ExpressionLanguage();
                    $isPostGroup = array_key_exists(TransformInterface::TRANSFORM_IS_POST_KEY, $transform) ? $transform[TransformInterface::TRANSFORM_IS_POST_KEY] : true;
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $addField) {
                        $transformObjects[] = new AddCalculatedFieldTransform($expressionLanguage, $addField, $isPostGroup);
                    }
                    break;

                case TransformInterface::ADD_CONDITION_VALUE_TRANSFORM:
                    $isPostGroup = array_key_exists(TransformInterface::TRANSFORM_IS_POST_KEY, $transform) ? $transform[TransformInterface::TRANSFORM_IS_POST_KEY] : true;
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $addConditionValue) {
                        $transformObjects[] = new AddConditionValueTransform($addConditionValue, $isPostGroup);
                    }
                    break;

                case TransformInterface::GROUP_TRANSFORM:
                    $aggregateAll = array_key_exists(GroupByTransform::AGGREGATE_ALL_KEY, $transform) ? $transform[GroupByTransform::AGGREGATE_ALL_KEY] : false;
                    $aggregationFields = array_key_exists(GroupByTransform::AGGREGATION_FIELDS_KEY, $transform) ? $transform[GroupByTransform::AGGREGATION_FIELDS_KEY] : [];
                    $timezone = array_key_exists(GroupByTransform::TIMEZONE_KEY, $transform) ? $transform[GroupByTransform::TIMEZONE_KEY] : GroupByTransform::DEFAULT_TIMEZONE;

                    $transformObjects[] = new GroupByTransform(
                        $transform[TransformInterface::FIELDS_TRANSFORM],
                        $aggregateAll,
                        $aggregationFields,
                        $timezone);
                    break;

                case TransformInterface::COMPARISON_PERCENT_TRANSFORM:
                    $isPostGroup = array_key_exists(TransformInterface::TRANSFORM_IS_POST_KEY, $transform) ? $transform[TransformInterface::TRANSFORM_IS_POST_KEY] : true;
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $comparisonField) {
                        $transformObjects[] = new ComparisonPercentTransform($comparisonField, $isPostGroup);
                    }
                    break;

                case TransformInterface::SORT_TRANSFORM:
                    $transformObjects[] = new SortByTransform($transform[TransformInterface::FIELDS_TRANSFORM]);
                    break;
                case TransformInterface::REPLACE_TEXT_TRANSFORM:
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $replaceTextField) {
                        $transformObjects[] = new ReplaceTextTransform($replaceTextField);
                    }
                    break;
            }
        }

        return $transformObjects;
    }

    /**
     * @param array $formats
     * @throws \Exception
     * @return array
     */
    protected function createFormats(array $formats)
    {
        $formatObjects = [];

        foreach ($formats as $format) {
            if (!array_key_exists(FormatInterface::FORMAT_TYPE_KEY, $format)) {
                throw new InvalidArgumentException('format "type" is missing');
            }

            switch ($format[FormatInterface::FORMAT_TYPE_KEY]) {
                case FormatInterface::FORMAT_TYPE_DATE:
                    $formatObjects[] = new DateFormat($format);

                    break;

                case FormatInterface::FORMAT_TYPE_NUMBER:
                    $formatObjects[] = new NumberFormat($format);

                    break;

                case FormatInterface::FORMAT_TYPE_CURRENCY:
                    $formatObjects[] = new CurrencyFormat($format);

                    break;

                case FormatInterface::FORMAT_TYPE_COLUMN_POSITION:
                    $formatObjects[] = new ColumnPositionFormat($format);

                    break;
                case FormatInterface::FORMAT_TYPE_PERCENTAGE:
                    $formatObjects[] = new PercentageFormat($format);

                    break;
            }
        }

        return $formatObjects;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportView(ReportViewInterface $reportView, $showInTotal = null, ParamsInterface $multiParams = null)
    {
        $param = new Params();
        $param->setSubView($reportView->isSubView());
        if ($reportView->isSubView()) {
            $param->setFilters(DataSet::createFilterObjects($reportView->getFilters()));
            if ($reportView->getMasterReportView() instanceof ReportViewInterface) {
                $reportView = $reportView->getMasterReportView();
            }
        }

        $dataSetsRawData = $this->reportViewDataSetObjectsToArray($reportView->getReportViewDataSets());
        $dataSets = $this->createDataSets($dataSetsRawData);
        $joinConfigs = $this->createJoinConfigs($reportView->getJoinBy(), $param->getDataSets());

        $param
            ->setDataSets($dataSets)
            ->setJoinConfigs($joinConfigs);
        // set showInTotal to NULL to get all total values
        // DO NOT change

        $param
            ->setTransforms($this->createTransforms($reportView->getTransforms()))
            ->setFieldTypes($reportView->getFieldTypes())
            ->setUserProvidedDimensionEnabled($reportView->isEnableCustomDimensionMetric());

        if (is_array($reportView->getWeightedCalculations())) {
            $param->setWeightedCalculations(new WeightedCalculation($reportView->getWeightedCalculations()));
        }

        if (is_array($reportView->getFormats())) {
            $param->setFormats($this->createFormats($reportView->getFormats()));
        }

        $param->setPage(1)->setLimit(10);

        return $param;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportViewForSharedReport(ReportViewInterface $reportView, array $fieldsToBeShared, array $paginationParams)
    {
        $param = new Params();
        $param->setReportView($reportView);
        $param->setReportViewId($reportView->getId());
        // important: the dimensions/metrics need re-calculate due to shared fields.
        // This makes sure group calculation is correct
        // e.g: dimensions are date-tag-country, if shared only date-tag => the shared report need be re-calculate base on
        // new dimensions are date-tag, not full date-tag-country as original report view
        //// do filtering dimensions
        $sharedDimensions = [];
        $dimensions = $reportView->getDimensions();

        foreach ($dimensions as $dimension) {
            if (!in_array($dimension, $fieldsToBeShared)) {
                continue;
            }

            $sharedDimensions[] = $dimension;
        }

        $param->setDimensions($sharedDimensions);

        //// do filtering metrics
        $sharedMetrics = [];
        $metrics = $reportView->getMetrics();
        foreach ($metrics as $metric) {
            if (!in_array($metric, $fieldsToBeShared)) {
                continue;
            }

            $sharedMetrics[] = $metric;
        }

        $param->setMetrics($sharedMetrics);
        $param->setSubView($reportView->isSubView());

        /*
         * VERY IMPORTANT: build report param same as above buildFromArray() function
         * report param:
         *      dataSets => required
         *      fieldTypes
         *      joinBy => required
         *      transforms
         *      weightedCalculations
         *      filters
         *      multiView
         *      showInTotal
         *      formats
         */
        if ($reportView->isSubView()) {
            $param->setFilters(DataSet::createFilterObjects($reportView->getFilters(), $overrideFilter = true));
            if ($reportView->getMasterReportView() instanceof ReportViewInterface) {
                $reportView = $reportView->getMasterReportView();
            }
        }

        $param->setDataSets($this->createDataSets(
            $this->reportViewDataSetObjectsToArray($reportView->getReportViewDataSets()))
        );
        $param->setJoinConfigs($this->createJoinConfigs($reportView->getJoinBy(), $param->getDataSets()));

        $param
            ->setTransforms($this->createTransforms($reportView->getTransforms()))
            ->setFieldTypes($reportView->getFieldTypes())
            ->setShowInTotal($reportView->getShowInTotal())
            ->setIsShowDataSetName($reportView->getIsShowDataSetName())
            ->setUserProvidedDimensionEnabled($reportView->isEnableCustomDimensionMetric());

        if (is_array($reportView->getWeightedCalculations())) {
            $param->setWeightedCalculations(new WeightedCalculation($reportView->getWeightedCalculations()));
        }

        if (is_array($reportView->getFormats())) {
            $param->setFormats($this->createFormats($reportView->getFormats()));
        }

        if (array_key_exists(self::START_DATE, $paginationParams) && !empty($paginationParams[self::START_DATE])) {
            $param->setStartDate(new \DateTime($paginationParams[self::START_DATE]));
        }

        if (array_key_exists(self::END_DATE, $paginationParams) && !empty($paginationParams[self::END_DATE])) {
            $param->setEndDate(new \DateTime($paginationParams[self::END_DATE]));
        }

        if (array_key_exists(self::ORDER_BY_KEY, $paginationParams)) {
            $param->setOrderBy($paginationParams[self::ORDER_BY_KEY]);
        }

        if (array_key_exists(self::SORT_FIELD_KEY, $paginationParams)) {
            $param->setSortField($paginationParams[self::SORT_FIELD_KEY]);
        }

        if (array_key_exists(self::PAGE_KEY, $paginationParams)) {
            $param->setPage(intval($paginationParams[self::PAGE_KEY]));
        }

        if (array_key_exists(self::LIMIT_KEY, $paginationParams)) {
            $param->setLimit(intval($paginationParams[self::LIMIT_KEY]));
        }

        if (array_key_exists(self::SEARCHES, $paginationParams)) {
            $searches = $paginationParams[self::SEARCHES];
            if (is_string($searches)) {
                $searches = json_decode($searches, true);
            }

            $param->setSearches($searches);
        }

        if (array_key_exists(self::USER_DEFINED_DIMENSIONS, $paginationParams)) {
            $userDefinedDimensions = $paginationParams[self::USER_DEFINED_DIMENSIONS];
            if (is_string($userDefinedDimensions)) {
                $userDefinedDimensions = json_decode($userDefinedDimensions, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($userDefinedDimensions)) {
                    $userDefinedDimensions = [];
                }
            } else {
                $userDefinedDimensions = [];
            }

            $param->setUserDefinedDimensions($userDefinedDimensions);
        } else {
            // if not user provide dimensions => set shared dimension to it
            // this is the quickest way for shareable report where dimensions/metrics are not fully shared
            $param->setUserDefinedDimensions($sharedDimensions);
        }

        if (array_key_exists(self::USER_DEFINED_METRICS, $paginationParams)) {
            $userDefinedMetrics = $paginationParams[self::USER_DEFINED_METRICS];
            if (is_string($userDefinedMetrics)) {
                $userDefinedMetrics = json_decode($userDefinedMetrics, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($userDefinedMetrics)) {
                    $userDefinedMetrics = [];
                }
            } else {
                $userDefinedMetrics = [];
            }

            $param->setUserDefinedMetrics($userDefinedMetrics);
        } else {
            // if not user provide metrics => set shared metrics to it
            // this is the quickest way for shareable report where dimensions/metrics are not fully shared
            $param->setUserDefinedMetrics($sharedMetrics);
        }

        return $param;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportViewAndParams(ReportViewInterface $reportView, $data)
    {
        //get all field
        $fullFields = $this->getFieldsFromReportView($reportView);
        $param = new Params();

        $param->setReportViewId($reportView->getId());

        $param->setSubView($reportView->isSubView());
        if ($reportView->isSubView()) {
            $param->setFilters(DataSet::createFilterObjects($reportView->getFilters(), $overrideFilter = true));
            if ($reportView->getMasterReportView() instanceof ReportViewInterface) {
                $reportView = $reportView->getMasterReportView();
            }
        }

        if (array_key_exists(self::REPORT_VIEW_ID, $data) && !empty($data[self::REPORT_VIEW_ID])) {
            $param->setReportViewId($reportView->getId());
        }

        $metricCalculations = [];
        $dataSetsRawData = $this->reportViewDataSetObjectsToArray($reportView->getReportViewDataSets());
        $dataSets = $this->createDataSets($dataSetsRawData);

        $joinConfigs = $this->createJoinConfigs($reportView->getJoinBy(), $param->getDataSets());

        $param
            ->setDataSets($dataSets)
            ->setJoinConfigs($joinConfigs);

        $param
            ->setDimensions($reportView->getDimensions())
            ->setMetrics($reportView->getMetrics())
            ->setTransforms($this->createTransforms($reportView->getTransforms()))
            ->setFieldTypes($reportView->getFieldTypes())
            ->setIsShowDataSetName($reportView->getIsShowDataSetName())
            ->setMetricCalculations($metricCalculations)
            ->setUserProvidedDimensionEnabled($reportView->isEnableCustomDimensionMetric());

        if (is_array($reportView->getWeightedCalculations())) {
            $param->setWeightedCalculations(new WeightedCalculation($reportView->getWeightedCalculations()));
        }

        if (is_array($reportView->getFormats())) {
            $param->setFormats($this->createFormats($reportView->getFormats()));
        }

        if (array_key_exists(self::START_DATE, $data) && !empty($data[self::START_DATE] && array_key_exists(self::END_DATE, $data) && !empty($data[self::END_DATE]))) {
            $startDate = $data[self::START_DATE];
            $endDate = $data[self::END_DATE];
            if ($startDate <= $endDate && $endDate <= date('Y-m-d')) {
                $param->setStartDate(new \DateTime($data[self::START_DATE]));
                $param->setEndDate(new \DateTime($data[self::END_DATE]));
            } else {
                throw new InvalidArgumentException('Invalid: startDate is not less than endDate or endDate is not less than today');
            }
        }

        if (array_key_exists(self::ORDER_BY_KEY, $data)) {
            $param->setOrderBy($data[self::ORDER_BY_KEY]);
        }

        if (array_key_exists(self::SORT_FIELD_KEY, $data)) {
            $param->setSortField($data[self::SORT_FIELD_KEY]);
        }

        if (array_key_exists(self::PAGE_KEY, $data)) {
            $param->setPage(intval($data[self::PAGE_KEY]));
        }

        if (array_key_exists(self::LIMIT_KEY, $data)) {
            $param->setLimit(intval($data[self::LIMIT_KEY]));
        }

        if (array_key_exists(self::SEARCHES, $data)) {
            $searches = $data[self::SEARCHES];
            if (is_string($searches)) {
                $searches = json_decode($searches, true);
            }
            $param->setSearches($searches);
        }

        if ($reportView->isEnableCustomDimensionMetric() == true) {
            if (array_key_exists(self::USER_DEFINED_DIMENSIONS, $data) && is_array($data[self::USER_DEFINED_DIMENSIONS]) && !empty($data[self::USER_DEFINED_DIMENSIONS])) {
                $userDefinedDimensions = $data[self::USER_DEFINED_DIMENSIONS];

                foreach ($userDefinedDimensions as $key => $value) {
                    if (!in_array($value, $fullFields)) {
                        unset($userDefinedDimensions[$key]);
                    }
                }

                //set user defined dimensions after delete the dimensions is invalid
                if (!empty($userDefinedDimensions)) {
                    $param->setUserDefinedDimensions($userDefinedDimensions);
                } else {
                    $param->setUserDefinedDimensions($reportView->getDimensions());
                }

            } else {
                $param->setUserDefinedDimensions($reportView->getDimensions());
            }

            if (array_key_exists(self::USER_DEFINED_METRICS, $data) && is_array($data[self::USER_DEFINED_METRICS]) && !empty($data[self::USER_DEFINED_METRICS])) {
                $userDefinedMetrics = $data[self::USER_DEFINED_METRICS];

                foreach ($userDefinedMetrics as $key => $value) {
                    if (!in_array($value, $fullFields)) {
                        unset($userDefinedMetrics[$key]);
                    }
                }

                // set user defined metric after delete the metrics is invalid
                if (!empty($userDefinedMetrics)) {
                    $param->setUserDefinedMetrics($userDefinedMetrics);
                } else {
                    $param->setUserDefinedMetrics($reportView->getMetrics());
                }
            } else {
                $param->setUserDefinedMetrics($reportView->getMetrics());
            }

            $transforms = $param->getTransforms();

            foreach ($transforms as &$transform) {
                if (!$transform instanceof GroupByTransform) {
                    continue;
                }

                $transform->setFields($param->getUserDefinedDimensions());
            }

            $param->setTransforms($transforms);
        }

        return $param;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportViewAndParamsForDashboard(ReportViewInterface $reportView, $data)
    {
        $param = $this->buildFromReportViewAndParams($reportView, $data);

        /* patch some params */
        // show in total: use [] for none instead of null for default metrics
        if (!array_key_exists(self::SHOW_IN_TOTAL_KEY, $data)) {
            $showInTotal = $reportView->getShowInTotal();
            $showInTotal = empty($showInTotal) ? [] : $showInTotal;
            $param->setShowInTotal($showInTotal);
        }

        return $param;
    }
}