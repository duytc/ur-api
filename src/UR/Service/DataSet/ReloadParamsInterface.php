<?php


namespace UR\Service\DataSet;

interface ReloadParamsInterface
{
    const ALL_DATA_TYPE = 'allData';
    const DETECTED_DATE_RANGE_TYPE = 'detectedDateRange';
    const IMPORTED_ON_TYPE = 'importedDate';
    
    const RELOAD_TYPE = 'reloadType';
    const RELOAD_START_DATE = 'reloadStartDate';
    const RELOAD_END_DATE = 'reloadEndDate';

    /**
     * @return mixed
     */
    public function getType();


    /**
     * @param mixed $type
     */
    public function setType($type);


    /**
     * @return mixed
     */
    public function getStartDate();


    /**
     * @param mixed $startDate
     * @return self
     */
    public function setStartDate($startDate);


    /**
     * @return mixed
     */
    public function getEndDate();

    /**
     * @param mixed $endDate
     * @return self
     */
    public function setEndDate($endDate);

}