<?php

namespace UR\Service\Parser\Filter;


use UR\Model\Core\AlertInterface;
use UR\Service\Import\ImportDataException;

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

    /**
     * @param $value
     * @return bool
     * @throws ImportDataException
     */
    public function filter($value)
    {
        $date = \DateTime::createFromFormat($this->format, $value);

        if (!$date) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_DATE, 0, $this->getField());
        }

        if ($date <= $this->endDate && $date >= $this->startDate) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws \Exception
     */
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