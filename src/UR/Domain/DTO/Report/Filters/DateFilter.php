<?php

namespace UR\Domain\DTO\Report\Filters;

use DateTime;
use UR\Service\DTO\Report\ReportResult;

class DateFilter extends AbstractFilter implements DateFilterInterface
{
    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const DATE_FORMAT_FILTER_KEY = 'format';
    const DATE_TYPE_FILTER_KEY = 'dateType';
    const DATE_VALUE_FILTER_KEY = 'dateValue';
    const DATE_USER_PROVIDED_FILTER_KEY = 'userProvided';

    /* for date type */
    const DATE_TYPE_CUSTOM_RANGE = 'customRange';
    const DATE_TYPE_DYNAMIC = 'dynamic';

    /* for date value in case "dateType" is customRange value */
    const DATE_VALUE_FILTER_START_DATE_KEY = 'startDate';
    const DATE_VALUE_FILTER_END_DATE_KEY = 'endDate';

    /* pre-defined date value in case "dateType" is customRange value */
    const DATE_DYNAMIC_VALUE_TODAY = 'today';
    const DATE_DYNAMIC_VALUE_YESTERDAY = 'yesterday';
    const DATE_DYNAMIC_VALUE_LAST_7_DAYS = 'last 7 days';
    const DATE_DYNAMIC_VALUE_LAST_30_DAYS = 'last 30 days';
    const DATE_DYNAMIC_VALUE_THIS_MONTH = 'this month';
    const DATE_DYNAMIC_VALUE_LAST_MONTH = 'last month';
    const DATE_DYNAMIC_VALUE_LAST_2_MONTH = 'last 2 months';
    const DATE_DYNAMIC_VALUE_LAST_3_MONTH = 'last 3 months';

    /* supported date types */
    public static $SUPPORTED_DATE_TYPES = [
        self::DATE_TYPE_CUSTOM_RANGE,
        self::DATE_TYPE_DYNAMIC
    ];

    /* supported date values in case "dateType" is customRange value */
    public static $SUPPORTED_DATE_DYNAMIC_VALUES = [
        self::DATE_DYNAMIC_VALUE_TODAY,
        self::DATE_DYNAMIC_VALUE_YESTERDAY,
        self::DATE_DYNAMIC_VALUE_LAST_7_DAYS,
        self::DATE_DYNAMIC_VALUE_LAST_30_DAYS,
        self::DATE_DYNAMIC_VALUE_THIS_MONTH,
        self::DATE_DYNAMIC_VALUE_LAST_MONTH
    ];

    /** @var string */
    protected $dateType;

    /** @var string|array */
    protected $dateValue;

    /** @var bool */
    protected $userDefine;

    /** @var string */
    protected $dateFormat;

    /**
     * @param array $dateFilter
     * @throws \Exception
     */
    public function __construct(array $dateFilter = null)
    {
        if (empty($dateFilter)) {
            return;
        }

        if (!array_key_exists(self::FIELD_TYPE_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::FILED_NAME_FILTER_KEY, $dateFilter)
            // || !array_key_exists(self::DATE_FORMAT_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::DATE_TYPE_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::DATE_VALUE_FILTER_KEY, $dateFilter)
        ) {
            throw new \Exception (sprintf('Either parameters: %s, %s, %s, %s or %s not exist in date filter',
                self::FIELD_TYPE_FILTER_KEY,
                self::FILED_NAME_FILTER_KEY,
                self::DATE_FORMAT_FILTER_KEY,
                self::DATE_TYPE_FILTER_KEY,
                self::DATE_VALUE_FILTER_KEY));
        }

        $this->fieldName = $dateFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $dateFilter[self::FIELD_TYPE_FILTER_KEY];
        // $this->dateFormat = $dateFilter[self::DATE_FORMAT_FILTER_KEY];
        $this->dateType = $dateFilter[self::DATE_TYPE_FILTER_KEY];
        $this->dateValue = $dateFilter[self::DATE_VALUE_FILTER_KEY];
        $this->userDefine = (bool)$dateFilter[self::DATE_USER_PROVIDED_FILTER_KEY];

        // validate dateValue
        $this->validateDateValue();
    }

    /**
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * @return string
     */
    public function getDateType()
    {
        return $this->dateType;
    }

    /**
     * @return array|string
     */
    public function getDateValue()
    {
        return $this->dateValue;
    }

    /**
     * @return array
     */
    public function getStartDate()
    {
        // notice:
        // userProvided is separate option with dateType
        // at first call, if dateType is dynamic, the startDate-endDate are empty,
        // so that we must get from dynamic
        // if call again with startDate-endDate that user provided, we must use them

        if ($this->isUserDefine()) {
            if (!is_array($this->dateValue) || !array_key_exists(self::DATE_VALUE_FILTER_START_DATE_KEY, $this->dateValue)) {
                // using user define but at the first time the dateValue may be empty
                // so that we need to get due to dateType
                // this occurs if dateType is dynamic
                if (self::DATE_TYPE_DYNAMIC == $this->dateType) {
                    return self::getDynamicDate($this->dateType, $this->dateValue)[0];
                }

                // else: customRange => invalid because missing startDate-endDate in dateValue
                return null; // todo: return null or throw an exception...
            } else {
                return $this->dateValue[self::DATE_VALUE_FILTER_START_DATE_KEY];
            }
        }

        // else: not using userProvided => use normal customRange or dynamic
        if (self::DATE_TYPE_CUSTOM_RANGE == $this->dateType) {
            return $this->dateValue[self::DATE_VALUE_FILTER_START_DATE_KEY];
        }

        // if (self::DATE_TYPE_DYNAMIC == $this->dateType)
        return self::getDynamicDate($this->dateType, $this->dateValue)[0];
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        // notice:
        // userProvided is separate option with dateType
        // at first call, if dateType is dynamic, the startDate-endDate are empty,
        // so that we must get from dynamic
        // if call again with startDate-endDate that user provided, we must use them

        if ($this->isUserDefine()) {
            if (!is_array($this->dateValue) || !array_key_exists(self::DATE_VALUE_FILTER_END_DATE_KEY, $this->dateValue)) {
                // using user define but at the first time the dateValue may be empty
                // so that we need to get due to dateType
                // this occurs if dateType is dynamic
                if (self::DATE_TYPE_DYNAMIC == $this->dateType) {
                    return self::getDynamicDate($this->dateType, $this->dateValue)[1];
                }

                // else: customRange => invalid because missing startDate-endDate in dateValue
                return null; // todo: return null or throw an exception...
            } else {
                return $this->dateValue[self::DATE_VALUE_FILTER_END_DATE_KEY];
            }
        }

        // else: not using userProvided => use normal customRange or dynamic

        if (self::DATE_TYPE_CUSTOM_RANGE == $this->dateType || $this->isUserDefine()) {
            return $this->dateValue[self::DATE_VALUE_FILTER_END_DATE_KEY];
        }

        // if (self::DATE_TYPE_DYNAMIC == $this->dateType)
        return self::getDynamicDate($this->dateType, $this->dateValue)[1];
    }

    /**
     * validate date value due to date type
     * @throws \Exception
     */
    private function validateDateValue()
    {
        if (!in_array($this->dateType, self::$SUPPORTED_DATE_TYPES)) {
            throw new \Exception (sprintf('not supported date type %s', $this->dateType));
        }

        if (self::DATE_TYPE_DYNAMIC == $this->dateType) {
            if (!in_array($this->dateValue, self::$SUPPORTED_DATE_DYNAMIC_VALUES) && is_string($this->dateValue)) {
                throw new \Exception (sprintf('not supported date value %s', $this->dateValue));
            }

            if (is_array($this->dateValue)) {
                throw new \Exception ('invalid dynamic date value');
            }
        }

        if (self::DATE_TYPE_CUSTOM_RANGE == $this->dateType) {
            if (!is_array($this->dateValue)
                || !array_key_exists(self::DATE_VALUE_FILTER_START_DATE_KEY, $this->dateValue)
                || !array_key_exists(self::DATE_VALUE_FILTER_END_DATE_KEY, $this->dateValue)
            ) {
                throw new \Exception (sprintf('missing either %s or %s in date value %s',
                    self::DATE_VALUE_FILTER_START_DATE_KEY,
                    self::DATE_VALUE_FILTER_END_DATE_KEY,
                    $this->dateValue));
            }
        }
    }

    /**
     * Get dynamic date from date value
     *
     * @param $oldDateValue
     * @param $newDateValue
     */
    public static function getTheLargestDynamicDate($oldDateValue, $newDateValue)
    {
        if (empty($oldDateValue)) {
            return $newDateValue;
        }

        $oldDateRange = self::getDynamicDate(self::DATE_TYPE_DYNAMIC, $oldDateValue);
        $newDateRange = self::getDynamicDate(self::DATE_TYPE_DYNAMIC, $newDateValue);

        if ($oldDateRange[0] != $newDateRange[0]) {
            return $oldDateRange[0] < $newDateRange[0] ? $oldDateValue : $newDateValue;
        }

        if ($oldDateRange[1] != $newDateRange[1]) {
            return $oldDateRange[1] > $newDateRange[1] ? $oldDateValue : $newDateValue;
        }

        return $newDateValue;
    }

    /**
     * Get fix date range
     *
     * @param array $oldDateRange
     * @param array $newDateRange
     *
     * @return array
     */
    public static function getTheLargestFixDate(array $oldDateRange, array $newDateRange)
    {
        $startDate = $oldDateRange[0] < $newDateRange[0] ? $oldDateRange[0] : $newDateRange[0];
        $endDate = $oldDateRange[1] > $newDateRange[1] ? $oldDateRange[1] : $newDateRange[1];

        if (empty($oldDateRange[0])) {
            $startDate = $newDateRange[0];
        }

        if (empty($oldDateRange[1])) {
            $endDate = $newDateRange[1];
        }

        if (empty($newDateRange[0])) {
            $startDate = $oldDateRange[0];
        }

        if (empty($newDateRange[1])) {
            $endDate = $oldDateRange[1];
        }

        return [$startDate, $endDate];
    }

    /**
     * get dynamic date from date value
     *
     * @param string $dateType
     * @param string $dateValue
     * @return array as [startDate, endDate], on fail => return ['', '']
     */
    public static function getDynamicDate($dateType, $dateValue)
    {
        if (self::DATE_TYPE_DYNAMIC != $dateType) {
            return ['', ''];
        }

        $startDate = '';
        $endDate = '';

        if (self::DATE_DYNAMIC_VALUE_TODAY == $dateValue) {
            $startDate = $endDate = date('Y-m-d', strtotime('now'));
        }

        if (self::DATE_DYNAMIC_VALUE_YESTERDAY == $dateValue) {
            $startDate = $endDate = date('Y-m-d', strtotime('-1 day'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_7_DAYS == $dateValue) {
            $startDate = date('Y-m-d', strtotime('-7 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_30_DAYS == $dateValue) {
            $startDate = date('Y-m-d', strtotime('-30 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
        }

        if (self::DATE_DYNAMIC_VALUE_THIS_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('this month'));
            $endDate = date('Y-m-d', strtotime('now'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('last month'));
            $endDate = date('Y-m-t', strtotime('last month'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_2_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('-2 month'));
            $endDate = date('Y-m-t', strtotime('-2 month'));
        }

        if (self::DATE_DYNAMIC_VALUE_LAST_3_MONTH == $dateValue) {
            $startDate = date('Y-m-01', strtotime('-3 month'));
            $endDate = date('Y-m-t', strtotime('-3 month'));
        }

        return [$startDate, $endDate];
    }

    public function doFilter(ReportResult $reportsCollections)
    {
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        if (!$startDate instanceof DateTime) {
            $startDate = new DateTime($startDate);
        }

        if (!$endDate instanceof DateTime) {
            $endDate = new DateTime($endDate);
        }

        $endDate->format('Y-m-d');
        $startDate->format('Y-m-d');

        $reports = $reportsCollections->getReports();
        $filterReports = array_filter($reports, function ($report) use ($startDate, $endDate) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                return false;
            }

            $stringDate = $report[$this->getFieldName()];
            $dateValue = new DateTime($stringDate);
            $dateValue->format('Y-m-d');

            if ($dateValue >= $startDate && $dateValue <= $endDate) {
                return true;
            }
            return false;

        }, ARRAY_FILTER_USE_BOTH);

        $reportsCollections->setReports($filterReports);

        return $reportsCollections;
    }

    /**
     * @param string $dateFormat
     * @return $this
     */
    public function setDateFormat(string $dateFormat)
    {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    /**
     * @param string $dateType
     * @return $this
     */
    public function setDateType(string $dateType)
    {
        $this->dateType = $dateType;
        return $this;
    }

    /**
     * @param array|string $dateValue
     * @return $this
     */
    public function setDateValue($dateValue)
    {
        $this->dateValue = $dateValue;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isUserDefine()
    {
        return $this->userDefine;
    }

    /**
     * @inheritdoc
     */
    public function setUserDefine($userDefine)
    {
        $this->userDefine = $userDefine;
        return $this;
    }
}