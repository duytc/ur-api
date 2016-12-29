<?php

namespace UR\Service\Parser\Filter;


use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Service\Alert\ProcessAlert;

class DateFilter extends AbstractFilter implements DateFilterInterface, ColumnFilterInterface
{
    const TYPE = 'type';
    const FIELD = 'field';
    const FORMAT = 'format';
    const START_DATE = 'startDate';
    const END_DATE = 'endDate';
    const DEFAULT_DATE_FORMAT = '!Y-m-d';

    protected $startDate;
    protected $endDate;
    protected $format;

    public function __construct(array $dateFilter)
    {
        if (count($dateFilter) !== 5) {
            throw new \Exception (sprintf('wrong date Filter configuration'));
        }

        if (!array_key_exists(self::TYPE, $dateFilter)
            || !array_key_exists(self::FIELD, $dateFilter)
            || !array_key_exists(self::FORMAT, $dateFilter)
            || !array_key_exists(self::START_DATE, $dateFilter)
            || !array_key_exists(self::END_DATE, $dateFilter)
        ) {
            throw new \Exception (sprintf('Either parameters: %s, %s, %s, %s or %s not exits in date filter',
                self::TYPE,
                self::FIELD,
                self::FORMAT,
                self::START_DATE,
                self::END_DATE));
        }

        $this->format = '!' . $dateFilter[self::FORMAT];
        $this->startDate = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $dateFilter[self::START_DATE]);
        $this->endDate = \DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $dateFilter[self::END_DATE]);
        $this->validate();
    }

    public function filter($value)
    {
        $value = \DateTime::createFromFormat($this->format, $value);

        if (!$value) {
            return ProcessAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE;
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