<?php


namespace UR\Domain\DTO\Report\DataSets;


use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Exception\InvalidArgumentException;
use UR\Service\Report\ReportBuilderConstant;

class DataSet implements DataSetInterface
{
    const DATA_SET_ID_KEY = 'dataSetId';
    const FILTERS_KEY = 'filters';
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';


    /**
     * @var int
     */
    protected $dataSetId;

    /**
     * @var array
     */
    protected $dimensions;

    /**
     * @var array
     */
    protected $metrics;

    /**
     * @var array
     */
    protected $filters;

    function __construct(array $data)
    {
        if (!array_key_exists(self::DATA_SET_ID_KEY, $data) || !array_key_exists(self::METRICS_KEY, $data) || !array_key_exists(self::DIMENSIONS_KEY, $data)) {
            throw new InvalidArgumentException('either "dataSetId" or "metrics" or "dimensions" is missing');
        }

        $this->dimensions = $data[self::DIMENSIONS_KEY];
        $this->dataSetId = $data[self::DATA_SET_ID_KEY];
        $this->metrics = $data[self::METRICS_KEY];

        if (!array_key_exists(self::FILTERS_KEY, $data)) {
            $this->filters = [];
        }

        if (empty($data[self::FILTERS_KEY])) {
            $this->filters = [];
        }

        $this->filters = $this->createFilterObjects($data[self::FILTERS_KEY]);
    }

    /**
     * @param array $allFilters
     * @throws \Exception
     * @return array
     */
    protected function createFilterObjects(array $allFilters)
    {
        $filterObjects = [];
        foreach ($allFilters as $filter) {

            if (!property_exists($filter, ReportBuilderConstant::FIELD_TYPE_FILTER_KEY)) {
                throw new \Exception(sprintf('Filter must have key = %s', ReportBuilderConstant::FIELD_TYPE_FILTER_KEY));
            }

            if ($filter->{ReportBuilderConstant::FIELD_TYPE_FILTER_KEY} == ReportBuilderConstant::DATE_FIELD_TYPE_FILTER_KEY) {

                $filedName = property_exists($filter, ReportBuilderConstant::FIELD_NAME_KEY) ?
                    $filter->{ReportBuilderConstant::FIELD_NAME_KEY} : null;
                $filedType = property_exists($filter, ReportBuilderConstant::FIELD_TYPE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::FIELD_TYPE_FILTER_KEY} : null;
                $formatDate = property_exists($filter, ReportBuilderConstant::DATE_FORMAT_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::DATE_FORMAT_FILTER_KEY} : null;
                $startDate = property_exists($filter, ReportBuilderConstant::START_DATE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::START_DATE_FILTER_KEY} : null;
                $endDate = property_exists($filter, ReportBuilderConstant::END_DATE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::END_DATE_FILTER_KEY} : null;


                $filterObjects[] = new DateFilter($filedName, $filedType, $formatDate, $startDate, $endDate);
            }

            if ($filter->{ReportBuilderConstant::FIELD_TYPE_FILTER_KEY} == ReportBuilderConstant::TEXT_FIELD_TYPE_FILTER_KEY) {

                $fieldName = property_exists($filter, ReportBuilderConstant::FIELD_NAME_KEY) ?
                    $filter->{ReportBuilderConstant::FIELD_NAME_KEY} : null;
                $fieldType = property_exists($filter, ReportBuilderConstant::FIELD_TYPE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::FIELD_TYPE_FILTER_KEY} : null;
                $comparisonType = property_exists($filter, ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY} : null;
                $comparisonValue = property_exists($filter, ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY} : null;

                $filterObjects[] = new TextFilter($fieldName, $fieldType, $comparisonType, $comparisonValue);
            }

            if ($filter->{ReportBuilderConstant::FIELD_TYPE_FILTER_KEY} == ReportBuilderConstant::NUMBER_FIELD_TYPE_FILTER_KEY) {

                $fieldName = property_exists($filter, ReportBuilderConstant::FIELD_NAME_KEY) ?
                    $filter->{ReportBuilderConstant::FIELD_NAME_KEY} : null;
                $fieldType = property_exists($filter, ReportBuilderConstant::FIELD_TYPE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::FIELD_TYPE_FILTER_KEY} : null;
                $comparisonType = property_exists($filter, ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY} : null;
                $comparisonValue = property_exists($filter, ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY) ?
                    $filter->{ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY} : null;

                $filterObjects[] = new NumberFilter($fieldName, $fieldType, $comparisonType, $comparisonValue);
            }
        }

        return $filterObjects;
    }

    /**
     * @inheritdoc
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @inheritdoc
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function getDataSetId()
    {
        return $this->dataSetId;
    }
}