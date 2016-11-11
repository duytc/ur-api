<?php


namespace UR\Domain\DTO\Report\DataSets;


use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Service\Report\ReportBuilderConstant;

class DataSet implements DataSetInterface
{
    protected $dataSetId;
    protected $dimensions;
    protected $metrics;
    protected $filters;

    function __construct($dataSetId, $dimensions, $filters, $metrics)
    {
        $this->dimensions = $dimensions;
        $this->filters = $this->createFilterObjects($filters);
        $this->metrics = $metrics;
        $this->dataSetId = $dataSetId;
    }

    /**
     * @param array $allFilters
     * @return array
     */
    protected function createFilterObjects(array $allFilters)
    {
        $filterObjects = [];
        foreach ($allFilters as $filter) {
            if ($filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY] == ReportBuilderConstant::DATE_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[] = new DateFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::DATE_FORMAT_FILTER_KEY],
                    $filter[ReportBuilderConstant::DATE_RANGE_FILTER_KEY]
                );
            }

            if (ReportBuilderConstant::FIELD_TYPE_FILTER_KEY == ReportBuilderConstant::TEXT_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[] = new TextFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY]
                );
            }

            if ($filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY] == ReportBuilderConstant::NUMBER_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[] = new NumberFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY]
                );
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