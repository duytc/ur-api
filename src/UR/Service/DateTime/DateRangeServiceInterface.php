<?php


namespace UR\Service\DateTime;

interface DateRangeServiceInterface
{
    /**
     * @param $dataSourceId
     * @return bool
     */
    public function calculateDateRangeForDataSource($dataSourceId);

    /**
     * @param $entryId
     * @return mixed
     */
    public function calculateDateRangeForDataSourceEntry($entryId);
}