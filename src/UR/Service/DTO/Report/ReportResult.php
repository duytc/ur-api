<?php


namespace UR\Service\DTO\Report;


use UR\Domain\DTO\Report\DateRange;

class ReportResult implements ReportResultInterface
{
    const REPORT_RESULT_REPORTS = 'reports';
    const REPORT_RESULT_TOTAL = 'total';
    const REPORT_RESULT_AVERAGE = 'average';
    const REPORT_RESULT_COLUMNS = 'columns';
    const REPORT_RESULT_TYPES = 'types';
    const REPORT_RESULT_DATE_RANGE = 'dateRange';

    /**
     * @var array
     */
    protected $reports;

    /**
     * @var array
     */
    protected $columns;

    /**
     * @var array
     */
    protected $total;

    /**
     * @var array
     */
    protected $average;

    /**
     * @var array
     */
    protected $types;

    /**
     * @var DateRange
     */
    protected $dateRange;

    /**
     * ReportResult constructor.
     * @param array $reports
     * @param array $total
     * @param array $average
     * @param array $dateRange
     * @param array $columns
     * @param array $types
     */
    public function __construct(array $reports, array $total, array $average, $dateRange, $columns = [], $types = [])
    {
        $this->reports = $reports;
        $this->total = $total;
        $this->average = $average;
        $this->columns = $columns;
        $this->types = $types;
        $this->dateRange = $dateRange;
    }

    /**
     * @inheritdoc
     */
    public function getReports()
    {
        return $this->reports;
    }

    /**
     * @inheritdoc
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @inheritdoc
     */
    public function getAverage()
    {
        return $this->average;
    }

    /**
     * @inheritdoc
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @inheritdoc
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @inheritdoc
     */
    public function getDateRange()
    {
        return $this->dateRange;
    }

    /**
     * @inheritdoc
     */
    public function setReports($reports)
    {
        $this->reports = $reports;
    }

    /**
     * @inheritdoc
     */
    public function setTotal($total)
    {
        $this->total = $total;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setAverage($average)
    {
        $this->average = $average;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setTypes($types)
    {
        $this->types = $types;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function toArray()
    {
        return [
            self::REPORT_RESULT_REPORTS => $this->reports,
            self::REPORT_RESULT_TOTAL => $this->total,
            self::REPORT_RESULT_AVERAGE => $this->average,
            self::REPORT_RESULT_COLUMNS => $this->columns,
            self::REPORT_RESULT_TYPES => $this->types,
            self::REPORT_RESULT_DATE_RANGE => $this->dateRange
        ];
    }
}