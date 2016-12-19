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
    public function getAddedFields();

    /**
     * generate for format
     * @param array $reports
     */
    public function setReports($reports);

    /**
     * generate for format
     * @param array $total
     */
    public function setTotal($total);

    /**
     * generate for format
     * @param array $average
     */
    public function setAverage($average);

    /**
     * generate for format
     * @param $columns
     * @return mixed
     */
    public function setColumns($columns);

    /**
     * get array of all elements
     * Note: use this if need to append other elements to reportResult without change its behavior
     * @return mixed
     */
    public function toArray();
}