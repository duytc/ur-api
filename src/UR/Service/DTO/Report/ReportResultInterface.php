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
}