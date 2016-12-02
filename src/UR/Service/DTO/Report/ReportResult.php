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
     * @var array
     */
    protected $types;

    /**
     * ReportResult constructor.
     * @param array $reports
     * @param array $total
     * @param array $average
     * @param array $columns
     * @param array $types
     */
    public function __construct(array $reports, array $total, array $average, $columns = [], $types = [])
    {
        $this->reports = $reports;
        $this->total = $total;
        $this->average = $average;
        $this->columns = $columns;
        $this->types = $types;
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

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
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
    }

    /**
     * @inheritdoc
     */
    public function setAverage($average)
    {
        $this->average = $average;
    }
}