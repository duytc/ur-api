<?php


namespace UR\Domain\DTO\Report\ReportViews;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Exception\InvalidArgumentException;

class ReportView implements ReportViewInterface
{
    const REPORT_VIEW_ID_KEY = 'subView';
    const FILTERS_KEY = 'filters';
    const TRANSFORMS_KEY = 'transform';
    const METRICS_KEY = 'metrics';
    const DIMENSIONS_KEY = 'dimensions';

    /**
     * @var int
     */
    protected $reportViewId;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var array
     */
    protected $metrics;

    /**
     * @var array
     */
    protected $dimensions;

    /**
     * ReportView constructor.
     * @param $data
     */
    public function __construct(array $data)
    {
        if (!array_key_exists(self::REPORT_VIEW_ID_KEY, $data) || !array_key_exists(self::METRICS_KEY, $data) || !array_key_exists(self::DIMENSIONS_KEY, $data)) {
            throw new InvalidArgumentException('either "reportViewId" or "metrics" or "dimensions" is missing');
        }

        $this->reportViewId = $data[self::REPORT_VIEW_ID_KEY];
        $this->dimensions = $data[self::DIMENSIONS_KEY];
        $this->metrics = $data[self::METRICS_KEY];

        if (!array_key_exists(self::FILTERS_KEY, $data)) {
            $this->filters = [];
        }

        if (empty($data[self::FILTERS_KEY])) {
            $this->filters = [];
        }

        $this->filters = DataSet::createFilterObjects($data[self::FILTERS_KEY]);
    }

    /**
     * @return int
     */
    public function getReportViewId()
    {
        return $this->reportViewId;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @return array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @return array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }
}