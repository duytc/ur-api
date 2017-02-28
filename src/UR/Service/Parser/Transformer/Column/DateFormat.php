<?php

namespace UR\Service\Parser\Transformer\Column;

use DateTime;

class DateFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    protected $fromDateFormat;
    protected $toDateFormat;
    protected $isCustomFormatDateFrom;
    private $dateFormat;

    public function __construct($field, $fromDateFormat = 'Y-m-d', $toDateFormat = 'Y-m-d', $isCustomFormatDateFrom = false)
    {
        parent::__construct($field);
        $this->fromDateFormat = $fromDateFormat;
        $this->toDateFormat = $toDateFormat;
        $this->isCustomFormatDateFrom = $isCustomFormatDateFrom;
    }

    public function transform($value)
    {
        if ($value instanceof DateTime) {
            return $value->format($this->toDateFormat);
        }

        $value = trim($value);

        if (strcmp($value, "0000-00-00") === 0 || $value === null || $value === "") {
            return null;
        }

        $fromDateFormat = $this->isCustomFormatDateFrom ? $this->convertCustomFromDateFormat($this->fromDateFormat) : $this->fromDateFormat;
        $date = DateTime::createFromFormat($fromDateFormat, $value);

        if (!$date) {
            return 2;
        }

        return $date->format($this->dateFormat);
    }

    public function getFromDateFormat()
    {
        return $this->fromDateFormat;
    }

    public function getToDateFormat()
    {
        return $this->toDateFormat;
    }

    /**
     * @param string $fromDateFormat
     */
    public function setFromDateFormat(string $fromDateFormat)
    {
        $this->fromDateFormat = $fromDateFormat;
    }

    /**
     * @param string $toDateFormat
     */
    public function setToDateFormat(string $toDateFormat)
    {
        $this->toDateFormat = $toDateFormat;
    }

    /**
     * @return mixed
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * @param mixed $dateFormat
     */
    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * @return boolean
     */
    public function isIsCustomFormatDateFrom()
    {
        return $this->isCustomFormatDateFrom;
    }

    /**
     * @param boolean $isCustomFormatDateFrom
     */
    public function setIsCustomFormatDateFrom($isCustomFormatDateFrom)
    {
        $this->isCustomFormatDateFrom = $isCustomFormatDateFrom;
    }

    /**
     * convert FromDateFormat To PHP format
     * e.g:
     * - YYYY.MM.DD => Y.m.d
     * - YYYY.MM.DDD => Y.m.D
     * - YYYY.MMM.DD => Y.M.d
     * - ...
     *
     * @param string $dateFormat
     * @return string|bool false if dateFormat is not a string
     */
    private function convertCustomFromDateFormat($dateFormat)
    {
        if (!is_string($dateFormat)) {
            return false;
        }

        // validate format: allow YY, YYYY, M, MM, MMM, MMMM, D, DD, and special characters . , - _ / <space>.
        // E.g YYYY.MMM.D is for 2017.02.1; YYYY MMMM, DD is for 2017 February, 19
        $dateFormatRegex = '/^([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})[\-,\.,\/,_\s]*([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})[\-,\.,\/,_\s]*([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})$/';
        if (preg_match($dateFormatRegex, $dateFormat, $matches) !== 1) {
            return false;
        }

        $convertedDateFormat = $dateFormat;

        // important: keep replacing MMMM before MMM, MMM before MM, MM before M and so on...
        $convertedDateFormat = str_replace('YYYY', 'Y', $convertedDateFormat); // 4 digits
        $convertedDateFormat = str_replace('YY', 'y', $convertedDateFormat); // 2 digits

        $convertedDateFormat = str_replace('MMMM', 'F', $convertedDateFormat); // full name
        $convertedDateFormat = str_replace('MMM', 'M', $convertedDateFormat); // 3 characters
        $convertedDateFormat = str_replace('MM', 'm', $convertedDateFormat); // 2 characters
        if (strpos($dateFormat, 'MMM') === false) { // need check if MMM is replaced by M before
            $convertedDateFormat = str_replace('M', 'n', $convertedDateFormat); // 1 character without leading zeros
        }

        $convertedDateFormat = str_replace('DD', 'd', $convertedDateFormat); // 2 characters
        if (strpos($dateFormat, 'DD') === false) { // need check if DD is replaced by D before
            $convertedDateFormat = str_replace('D', 'j', $convertedDateFormat); // 1 character without leading zeros
        }

        return $convertedDateFormat;
    }
}