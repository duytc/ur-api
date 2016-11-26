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
}