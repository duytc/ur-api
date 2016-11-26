<?php


namespace UR\Service\DTO\Report;


class ReportResult implements ReportResultInterface
{
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
     * ReportResult constructor.
     * @param array $reports
     * @param array $total
     * @param array $average
     * @param array $columns
     */
    public function __construct(array $reports, array $total, array $average, $columns = [])
    {
        $this->reports = $reports;
        $this->total = $total;
        $this->average = $average;
        $this->columns = $columns;
    }

    /**
     * @return array
     */
    public function getReports()
    {
        return $this->reports;
    }

    /**
     * @return array
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @return array
     */
    public function getAverage()
    {
        return $this->average;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }
}