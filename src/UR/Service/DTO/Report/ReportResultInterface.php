<?php


namespace UR\Service\DTO\Report;


interface ReportResultInterface
{
    /**
     * @return array
     */
    public function getReports();

    /**
     * @return array
     */
    public function getTotal();

    /**
     * @return array
     */
    public function getAverage();

    /**
     * @return array
     */
    public function getColumns();

    /**
     * @return array
     */
    public function getTypes();

    /**
     * @param array $reports
     * @return self
     */
    public function setReports($reports);

    /**
     * @param array $total
     * @return self
     */
    public function setTotal($total);

    /**
     * @param array $average
     * @return self
     */
    public function setAverage($average);

    /**
     * @param $columns
     * @return self
     */
    public function setColumns($columns);

    /**
     * @param array $types
     * @return self
     */
    public function setTypes($types);

    /**
     * @return mixed
     */
    public function getTotalPage();

    /**
     * @param mixed $totalPage
     * @return self
     */
    public function setTotalPage($totalPage);

    /**
     * @return int
     */
    public function getTotalReport();

    /**
     * @param int $totalReport
     * @return self
     */
    public function setTotalReport($totalReport);

    /**
     * get array of all elements
     * Note: use this if need to append other elements to reportResult without change its behavior
     * @return mixed
     */
    public function toArray();
}