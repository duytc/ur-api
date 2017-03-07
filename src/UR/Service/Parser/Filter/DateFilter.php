<?php

namespace UR\Service\Parser\Filter;


use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;

class DateFilter extends AbstractFilter implements ColumnFilterInterface
{
    const FORMAT_FILTER_KEY = 'format';
    const START_DATE_FILTER_KEY = 'startDate';
    const END_DATE_FILTER_KEY = 'endDate';
    const DEFAULT_DATE_FORMAT = '!Y-m-d';

    protected $startDate;
    protected $endDate;
    protected $format;

    /**
     * DateFilter constructor.
     * @param $field
     * @param $startDate
     * @param $endDate
     * @param $format
     */
    public function __construct($field, $startDate, $endDate, $format)
    {
        parent::__construct($field);
        $this->startDate = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $startDate);
        $this->endDate = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $endDate);
        $this->format = '!' . $format;
    }


    public function filter($value)
    {
        $value = \DateTime::createFromFormat($this->format, $value);

        if (!$value) {
            return ImportFailureAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE;
        }

        if ($value <= $this->endDate && $value >= $this->startDate) {
            return true;
        }

        return false;
    }

    public function validate()
    {
        if (!$this->startDate || !$this->endDate) {
            throw new \Exception (sprintf('cannot get date value from this date range'));
        }

        return true;
    }

    /**
     * @return string
     */
    public function getDateFormat()
    {
        return $this->format;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->startDate;
    }

    /**
     * @return array
     */
    public function getStartDate()
    {
        return $this->endDate;
    }
}