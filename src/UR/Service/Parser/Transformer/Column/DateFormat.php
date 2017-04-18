<?php

namespace UR\Service\Parser\Transformer\Column;

use DateTime;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
use UR\Service\Import\ImportDataException;

class DateFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    const FROM_KEY = 'from';
    const TO_KEY = 'to';
    const IS_CUSTOM_FORMAT_DATE_FROM = 'isCustomFormatDateFrom';
    const DEFAULT_DATE_FORMAT = 'Y-m-d';

    protected $fromDateFormat;
    protected $toDateFormat;
    protected $isCustomFormatDateFrom;

    private $supportedDateFormats = [
        'Y-m-d',  // 2016-01-15
        'Y/m/d',  // 2016/01/15
        'm-d-Y',  // 01-15-2016
        'm/d/Y',  // 01/15/2016
        'd-m-Y',  // 15/01/2016
        'd/m/Y',  // 15/01/2016
        'Y-M-d',  // 2016-Mar-01
        'Y/M/d',  // 2016/Mar/01
        'M-d-Y',  // Mar-01-2016
        'M/d/Y',  // Mar/01/2016
        'd-M-Y',  // 01-Mar-2016
        'd/M/Y',  // 01/Mar/2016
        'M d, Y', // Mar 01,2016
        'Y, M d', // 2016, Mar 01
        'd-m-y',  // 15-01-99
        'd/m/y',  // 15/01/99
        'm-d-y',  // 01-15-99
        'm/d/y',  // 01/15/99
        'y-m-d',  // 99-01-15
        'y/m/d',  // 99/01/15
    ];

    public function __construct($field, $fromDateFormat = 'Y-m-d', $toDateFormat = 'Y-m-d', $isCustomFormatDateFrom = false)
    {
        parent::__construct($field);
        $this->fromDateFormat = $fromDateFormat;
        $this->toDateFormat = $toDateFormat;
        $this->isCustomFormatDateFrom = $isCustomFormatDateFrom;
    }

    /**
     * @inheritdoc
     */
    public function transform($value)
    {
        if ($value instanceof DateTime) {
            return $value->format(self::DEFAULT_DATE_FORMAT);
        }

        $value = trim($value);

        if ($value === null || $value === "") {
            return null;
        }

        $fromDateFormat = $this->isCustomFormatDateFrom ? $this->convertCustomFromDateFormat($this->fromDateFormat) : $this->fromDateFormat;
        $date = DateTime::createFromFormat($fromDateFormat, $value);

        //throw exception when wrong date value or format
        if (!$date instanceof DateTime) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE, 0, $this->getField());
        }

        return $date->format(self::DEFAULT_DATE_FORMAT);
    }

    /**
     * reformat date from default Y-m-d to another format
     * @param $value
     * @return null|string
     */
    public function transformFromDatabaseToClient($value)
    {
        if ($value instanceof DateTime) {
            return $value->format($this->toDateFormat);
        }

        $date = DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $value);

        if ($value === '0000-00-00' || !$date instanceof DateTime) {
            return null;
        }

        return $date->format($this->toDateFormat);
    }

    /**
     * @return string
     */
    public function getFromDateFormat()
    {
        return $this->fromDateFormat;
    }

    /**
     * @return string
     */
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
        $dateFormatRegex = '/^([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})[\-,\.,\/,_\s]*([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})[\-,\.,\/,_\s]*([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})((\\\[A-Za-z]|\s)(.*))?$/';
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

    /**
     * @inheritdoc
     */
    public function validate()
    {
        if (!$this->isCustomFormatDateFrom && !in_array($this->fromDateFormat, $this->supportedDateFormats)) {
            // validate using builtin data formats (when isCustomFormatDateFrom = false)
            throw  new BadRequestHttpException(sprintf('Transform setting error: field "%s" not support from date format', $this->getField()));
        }

        if (!in_array($this->toDateFormat, $this->supportedDateFormats)) {
            throw  new BadRequestHttpException(sprintf('Transform setting error: field "%s" not support to date format', $this->getField()));
        }
    }
}