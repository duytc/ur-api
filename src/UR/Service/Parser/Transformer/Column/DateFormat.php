<?php

namespace UR\Service\Parser\Transformer\Column;

use DateTime;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;

class DateFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    const DEFAULT_TIMEZONE = 'UTC';
    const FROM_KEY = 'from';
    const TO_KEY = 'to';
    const IS_CUSTOM_FORMAT_DATE_FROM = 'isCustomFormatDateFrom';
    const DEFAULT_DATE_FORMAT = 'Y-m-d';
    const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';
    const TIMEZONE_KEY = 'timezone';

    const FORMAT_KEY = 'format';

    protected $fromDateFormats;
    protected $toDateFormat;
    protected $timezone;
    const SUPPORTED_DATE_FORMATS = [
        self::DEFAULT_DATE_FORMAT,  // 2016-01-15
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
        'Y-m-d H:i',
        self::DEFAULT_DATETIME_FORMAT,
        'Y-m-d H:i:s T',
    ];

    /**
     * DateFormat constructor.
     * @param $field
     * @param array $fromDateFormats
     * @param string $timezone
     * @param string $toDateFormat
     */
    public function __construct($field, array $fromDateFormats, $timezone = self::DEFAULT_TIMEZONE, $toDateFormat = self::DEFAULT_DATE_FORMAT)
    {
        parent::__construct($field);
        $this->fromDateFormats = $fromDateFormats;
        $this->toDateFormat = $toDateFormat;
        $this->timezone = $timezone;
    }

    /**
     * @inheritdoc
     */
    public function transform($value)
    {
        if ($value instanceof DateTime) {
            return $value->format($this->toDateFormat);
        }

        $date = DateTime::createFromFormat('Y-m-d H:i:s T', $value);
        if ($date instanceof DateTime) {
            return $date->format($this->toDateFormat);
        }

        $value = trim($value);

        if ($value === null || $value === "") {
            return null;
        }

        $resultDate = null;
        foreach ($this->fromDateFormats as $fromDateFormat) {
            //get is custom date format for each format
            $isCustomDateFormat = array_key_exists(self::IS_CUSTOM_FORMAT_DATE_FROM, $fromDateFormat) ? $fromDateFormat[self::IS_CUSTOM_FORMAT_DATE_FROM] : false;

            //get from date format
            $fromFormat = array_key_exists(self::FORMAT_KEY, $fromDateFormat) ? $fromDateFormat[self::FORMAT_KEY] : null;
            $fromFormat = $isCustomDateFormat ? self::convertCustomFromDateFormat($fromFormat) : $fromFormat;

            $date = DateTime::createFromFormat($fromFormat, $value, new \DateTimeZone($this->timezone));

            if (!$date instanceof DateTime) {
                continue;
            }

            $date->setTimezone(new \DateTimeZone(self::DEFAULT_TIMEZONE));
            $resultDate = $date;
        }

        //throw exception when wrong date value or format
        if (!$resultDate instanceof DateTime) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE, 0, $this->getField());
        }

        switch ($this->getToDateFormat()) {
            case self::DEFAULT_DATETIME_FORMAT:
                return $resultDate->format(self::DEFAULT_DATETIME_FORMAT);
            default:
                return $resultDate->format(self::DEFAULT_DATE_FORMAT);
        }
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
        if (!$date instanceof DateTime) {
            $date = DateTime::createFromFormat(self::DEFAULT_DATETIME_FORMAT, $value);
        }

        if ($value === '0000-00-00' || !$date instanceof DateTime) {
            return null;
        }

        return $date->format($this->toDateFormat);
    }

    /**
     * @return string
     */
    public function getFromDateFormats()
    {
        return $this->fromDateFormats;
    }

    /**
     * @return string
     */
    public function getToDateFormat()
    {
        return $this->toDateFormat;
    }

    /**
     * @param string $fromDateFormats
     */
    public function setFromDateFormats(string $fromDateFormats)
    {
        $this->fromDateFormats = $fromDateFormats;
    }

    /**
     * @param string $toDateFormat
     */
    public function setToDateFormat(string $toDateFormat)
    {
        $this->toDateFormat = $toDateFormat;
    }

    /**
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @param string $timezone
     * @return self
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
        return $this;
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
    public static function convertCustomFromDateFormat($dateFormat)
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
        $isSupportDateFormat = true;
        foreach ($this->fromDateFormats as $fromDateFormat) {
            //check non-custom date formats, if one of them is not supported, throw exception
            $isCustomDateFormat = array_key_exists(self::IS_CUSTOM_FORMAT_DATE_FROM, $fromDateFormat) ? $fromDateFormat[self::IS_CUSTOM_FORMAT_DATE_FROM] : false;
            $fromFormat = array_key_exists(self::FORMAT_KEY, $fromDateFormat) ? $fromDateFormat[self::FORMAT_KEY] : null;
            if ($isCustomDateFormat !== true && !in_array($fromFormat, self::SUPPORTED_DATE_FORMATS)) {
                $isSupportDateFormat = false;
                break;
            }
        }

        if (!$isSupportDateFormat) {
            // validate using builtin data formats (when isCustomFormatDateFrom = false)
            throw  new BadRequestHttpException(sprintf('Transform setting error: field "%s" not support from date format', $this->getField()));
        }

        if (!in_array($this->toDateFormat, self::SUPPORTED_DATE_FORMATS)) {
            throw  new BadRequestHttpException(sprintf('Transform setting error: field "%s" not support to date format', $this->getField()));
        }
    }

    /**
     * @param $value
     * @param $column
     * @param $connectedDataSource
     * @return string
     */
    public static function getDateFromDateTime($value, $column = null, $connectedDataSource = null)
    {
        $timeZone = self::getTimeZoneOfDateField($column, $connectedDataSource);
        foreach (self::SUPPORTED_DATE_FORMATS as $format) {
            $dateTime = date_create_from_format($format, $value, new \DateTimeZone($timeZone));
            if ($dateTime) {
                $dateTime->setTimezone(new \DateTimeZone(self::DEFAULT_TIMEZONE));
                return $dateTime->format(self::DEFAULT_DATE_FORMAT);
            }
        }

        /** For user provided datetime format */
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return '';
        }

        $mapFields = $connectedDataSource->getMapFields();
        if (!array_key_exists($column, $mapFields)) {
            return '';
        }
        $field = $mapFields[$column];

        $transforms = $connectedDataSource->getTransforms();
        foreach ($transforms as $transform) {
            if (!array_key_exists(CollectionTransformerInterface::TYPE_KEY, $transform)) {
                continue;
            }
            if ($transform[CollectionTransformerInterface::TYPE_KEY] != ColumnTransformerInterface::DATE_FORMAT) {
                continue;
            }

            if (!array_key_exists(CollectionTransformerInterface::FIELD_KEY, $transform)) {
                continue;
            }
            if ($transform[CollectionTransformerInterface::FIELD_KEY] != $field) {
                continue;
            }

            if (!array_key_exists(self::FROM_KEY, $transform)) {
                continue;
            }

            if (array_key_exists(GroupByTransform::TIMEZONE_KEY, $transform)) {
                $timeZone = $transform[GroupByTransform::TIMEZONE_KEY];
            } else {
                $timeZone = self::DEFAULT_TIMEZONE;
            }

            $fromFormats = $transform[self::FROM_KEY];

            foreach ($fromFormats as $fromFormat) {
                if (!array_key_exists(self::FORMAT_KEY, $fromFormat)) {
                    continue;
                }
                $format = self::convertCustomFromDateFormat($fromFormat[self::FORMAT_KEY]);
                $dateTime = date_create_from_format($format, $value, new \DateTimeZone($timeZone));
                if ($dateTime) {
                    $dateTime->setTimezone(new \DateTimeZone(self::DEFAULT_TIMEZONE));
                    return $dateTime->format(self::DEFAULT_DATE_FORMAT);
                }
            }
        }
        return '';
    }

    public static function getTimeZoneOfDateField($column = null, $connectedDataSource = null)
    {
        /** For user provided datetime format */
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return self::DEFAULT_TIMEZONE;
        }

        $mapFields = $connectedDataSource->getMapFields();
        if (!array_key_exists($column, $mapFields)) {
            return self::DEFAULT_TIMEZONE;
        }
        $field = $mapFields[$column];

        $transforms = $connectedDataSource->getTransforms();
        foreach ($transforms as $transform) {
            if (!array_key_exists(CollectionTransformerInterface::TYPE_KEY, $transform)) {
                continue;
            }
            if ($transform[CollectionTransformerInterface::TYPE_KEY] != ColumnTransformerInterface::DATE_FORMAT) {
                continue;
            }

            if (!array_key_exists(CollectionTransformerInterface::FIELD_KEY, $transform)) {
                continue;
            }
            if ($transform[CollectionTransformerInterface::FIELD_KEY] != $field) {
                continue;
            }

            if (!array_key_exists(self::FROM_KEY, $transform)) {
                continue;
            }

            if (array_key_exists(GroupByTransform::TIMEZONE_KEY, $transform)) {
                return $transform[GroupByTransform::TIMEZONE_KEY];
            }
        }
        return self::DEFAULT_TIMEZONE;
    }
}