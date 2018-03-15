<?php


namespace UR\Service\DataSet;

use DateTime;

interface ReloadParamsInterface
{
    const ALL_DATA_TYPE = 'allData';
    const DETECTED_DATE_RANGE_TYPE = 'detectedDateRange';
    const IMPORTED_ON_TYPE = 'importedDate';

    const RELOAD_TYPE = 'reloadType';
    const RELOAD_START_DATE = 'reloadStartDate';
    const RELOAD_END_DATE = 'reloadEndDate';

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     */
    public function setType($type);

    /**
     * @return DateTime|null
     */
    public function getStartDate();

    /**
     * @param DateTime|null $startDate
     * @return self
     */
    public function setStartDate($startDate);

    /**
     * @return DateTime|null
     */
    public function getEndDate();

    /**
     * @param DateTime|null $endDate
     * @return self
     */
    public function setEndDate($endDate);
}