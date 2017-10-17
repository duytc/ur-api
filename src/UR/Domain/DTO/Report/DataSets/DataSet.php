<?php


namespace UR\Domain\DTO\Report\DataSets;


use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Exception\InvalidArgumentException;

class DataSet implements DataSetInterface
{
    const DATA_SET_ID_KEY = 'dataSet';
    const FILTERS_KEY = 'filters';
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const DATE_FORMAT_FILTER_KEY = 'format';
    const START_DATE_FILTER_KEY = 'startDate';
    const END_DATE_FILTER_KEY = 'endDate';

    const COMPARISON_TYPE_FILTER_KEY = 'comparison';
    const COMPARISON_VALUE_FILTER_KEY = 'compareValue';

    const DATE_FIELD_TYPE_FILTER_KEY = 'date';
    const NUMBER_FIELD_TYPE_FILTER_KEY = 'number';
    const TEXT_FIELD_TYPE_FILTER_KEY = 'text';
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

        if (!is_array($data[self::FILTERS_KEY])) {
            $this->filters = [];
        } else {
            $this->filters = $data[self::FILTERS_KEY];
        }

        $this->filters = $this->createFilterObjects($this->filters);
    }

    /**
     * @param array $allFilters
     * @param bool $overrideFilter
     * @return array
     * @throws \Exception
     */
    public static function createFilterObjects(array $allFilters, $overrideFilter = false)
    {
        $filterObjects = [];
        foreach ($allFilters as $filter) {

            if (!array_key_exists(self::FIELD_TYPE_FILTER_KEY, $filter)) {
                throw new \Exception(sprintf('Filter must have key = %s', self::FIELD_TYPE_FILTER_KEY));
            }

            if ($overrideFilter) {
                $filter[AbstractFilter::FILTER_FIELD_KEY] = sprintf('%s_%d', $filter[AbstractFilter::FILTER_FIELD_KEY], $filter[AbstractFilter::FILTER_DATA_SET_KEY]);
            }

            $filterType = $filter[self::FIELD_TYPE_FILTER_KEY];

            switch ($filterType) {
                case self::DATE_FIELD_TYPE_FILTER_KEY:
                    $filterObjects[] = new DateFilter($filter);
                    break;
                case self::TEXT_FIELD_TYPE_FILTER_KEY:
                    $filterObjects[] = new TextFilter($filter);
                    break;
                case self:: NUMBER_FIELD_TYPE_FILTER_KEY:
                    $filterObjects[] = new NumberFilter($filter);
                    break;
                default:
                    break;
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

    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }
}