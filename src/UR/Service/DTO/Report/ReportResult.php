<?php


namespace UR\Service\DTO\Report;


use SplDoublyLinkedList;
use UR\Domain\DTO\Report\DateRange;

class ReportResult implements ReportResultInterface
{
    const REPORT_RESULT_REPORTS = 'reports';
    const REPORT_RESULT_TOTAL = 'total';
    const REPORT_RESULT_AVERAGE = 'average';
    const REPORT_RESULT_COLUMNS = 'columns';
    const REPORT_RESULT_TYPES = 'types';
    const REPORT_RESULT_DATE_RANGE = 'dateRange';
    const REPORT_RESULT_TOTAL_REPORT = 'totalReport';


    /**
     * @var array
     */
    protected $reports;

    /**
     * @var SplDoublyLinkedList
     */
    protected $rows;

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

    protected $totalPage;

    protected $totalReport;

    /**
     * ReportResult constructor.
     * @param SplDoublyLinkedList|null $rows
     * @param array $total
     * @param array $average
     * @param array $dateRange
     * @param array $columns
     * @param array $types
     * @param int $totalReport
     */
    public function __construct($rows, array $total, array $average, $dateRange, $columns = [], $types = [], $totalReport = 0)
    {
        $this->rows = $rows;
        $this->total = $total;
        $this->average = $average;
        $this->columns = $columns;
        $this->types = $types;
        $this->dateRange = $dateRange;
        $this->totalReport = $totalReport;
        $this->reports = [];
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
     * @return SplDoublyLinkedList
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param SplDoublyLinkedList $rows
     * @return self
     */
    public function setRows($rows)
    {
        $this->rows = $rows;
        return $this;
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
    public function setDateRange(DateRange $dateRange)
    {
        $this->dateRange = $dateRange;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTotalPage()
    {
        return $this->totalPage;
    }

    /**
     * @param mixed $totalPage
     * @return self
     */
    public function setTotalPage($totalPage)
    {
        $this->totalPage = $totalPage;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalReport()
    {
        return $this->totalReport;
    }

    /**
     * @param int $totalReport
     * @return self
     */
    public function setTotalReport($totalReport)
    {
        $this->totalReport = $totalReport;
        return $this;
    }

    public function generateReports()
    {
        if (!$this->rows instanceof SplDoublyLinkedList) {
            return $this;
        }

        $this->reports = [];
        foreach ($this->rows as $row) {
            $this->reports[] = $row;
        }

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
            self::REPORT_RESULT_DATE_RANGE => is_array($this->dateRange) ? $this->dateRange : [],
            self::REPORT_RESULT_TOTAL_REPORT => $this->totalReport
        ];
    }
}