<?php

namespace UR\Service\Parser\Transformer\Column;

use DateTime;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;

class DateFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    const DEFAULT_TIMEZONE = 'UTC';
    const FROM_KEY = 'from';
    const TO_KEY = 'to';
    const IS_CUSTOM_FORMAT_DATE_FROM = 'isCustomFormatDateFrom';
    const DEFAULT_DATE_FORMAT = 'Y-m-d'; // in PHP
    const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s'; // in PHP
    const DEFAULT_DATE_FORMAT_FULL = 'YYYY-MM-DD'; // full text
    const DEFAULT_DATETIME_FORMAT_FULL = 'YYYY-MM-DD HH:mm:ss'; // full text
    const TIMEZONE_KEY = 'timezone';

    const FORMAT_KEY = 'format';

    /**
     * full date format regex
     * The full date format may be YYYY/MM/DD, YYYY-MM-DD HH:mm:ss, ...
     */
    const FULL_DATE_FORMAT_REGEX = '/^([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})[\-,\.,\/,_\s]*([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})[\-,\.,\/,_\s]*([Y]{2}|[Y]{4}|[M]{1,4}|[D]{1,2})*(([T]{1}|[H]{2,2}|[m]{2,2}|[s]{2,2})|[\\-,\.,\/,_:\s])*((e?)|(O?)|(P?)|(T?))?$/';

    protected $fromDateFormats;
    protected $toDateFormat;
    protected $timezone;

    /**
     * supported date formats as [ key => value ],
     * key is submitted value from UI, and value is real php date format
     */
    const SUPPORTED_DATE_FORMATS = [
        // Support 4 digit years
        self::DEFAULT_DATE_FORMAT_FULL => self::DEFAULT_DATE_FORMAT,  // 2016-01-15
        'YYYY/MM/DD' => 'Y/m/d',  // 2016/01/15
        'MM-DD-YYYY' => 'm-d-Y',  // 01-15-2016
        'MM/DD/YYYY' => 'm/d/Y',  // 01/15/2016
        'DD-MM-YYYY' => 'd-m-Y',  // 15/01/2016
        'DD/MM/YYYY' => 'd/m/Y',  // 15/01/2016
        'YYYY-MMM-DD' => 'Y-M-d',  // 2016-Mar-01
        'YYYY/MMM/DD' => 'Y/M/d',  // 2016/Mar/01
        'MMM-DD-YYYY' => 'M-d-Y',  // Mar-01-2016
        'MMM/DD/YYYY' => 'M/d/Y',  // Mar/01/2016
        'DD-MMM-YYYY' => 'd-M-Y',  // 01-Mar-2016
        'DD/MMM/YYYY' => 'd/M/Y',  // 01/Mar/2016
        'MMM DD, YYYY' => 'M d, Y', // Mar 01,2016
        'YYYY, MMM DD' => 'Y, M d', // 2016, Mar 01

        // Support 2 digit years
        'MM/DD/YY' => 'm/d/y',  // 01/15/99
        'MM-DD-YY' => 'm-d-y',  // 01-15-99
        'DD/MM/YY' => 'd/m/y',  // 15/01/99
        'DD-MM-YY' => 'd-m-y',  // 15-01-99
        'YY/MM/DD' => 'y/m/d',  // 99/01/15
        'YY-MM-DD' => 'y-m-d',  // 99-01-15

        // Support time
        self::DEFAULT_DATETIME_FORMAT_FULL => self::DEFAULT_DATETIME_FORMAT,
        'YYYY-MM-DD HH:mm' => 'Y-m-d H:i',

        // Support time with timezone
        'YYYY-MM-DD HH:mm:ss T' => 'Y-m-d H:i:s T', // 2017-06-12 20:00:00 GMT+0000
        'YYYY-MM-DD HH:mm:ss e' => 'Y-m-d H:i:s e', // 2017-06-12 20:00:00 +00:00
        'YYYY-MM-DD HH:mm:ss O' => 'Y-m-d H:i:s O', // 2017-06-12 20:00:00 +0000
        'YYYY-MM-DD HH:mm:ss P' => 'Y-m-d H:i:s P'  // 2017-06-12 20:00:00 +00:00
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

        if (empty($timezone)) {
            $this->timezone = self::DEFAULT_TIMEZONE;
        } else {
            $this->timezone = $timezone;
        }
    }

    /**
     * @inheritdoc
     */
    public function transform($value)
    {
        if ($value instanceof DateTime) {
            return $value->format($this->getToDateFormatInPHPFormat());
        }

        $date = DateTime::createFromFormat(GroupByColumns::TEMPORARY_DATE_FORMAT, $value);
        if ($date instanceof DateTime) {
            return $date->format($this->getToDateFormatInPHPFormat());
        }

        $value = trim($value);

        if ($value === null || $value === "") {
            return null;
        }

        $resultDate = null;
        foreach ($this->fromDateFormats as $fromDateFormat) {
            //get from date format
            $fromFormat = array_key_exists(self::FORMAT_KEY, $fromDateFormat) ? $fromDateFormat[self::FORMAT_KEY] : null;
            if (array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $fromDateFormat) && $fromDateFormat[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM]) {
                $fromFormat = self::convertCustomFromDateFormatToPHPDateFormat($fromFormat);
            } else {
                $fromFormat = self::convertCustomFromDateFormatToPHPDateFormat($fromFormat);
            }

            $date = DateTime::createFromFormat($fromFormat, $value, new \DateTimeZone($this->timezone));

            if (!$date instanceof DateTime) {
                continue;
            }

            $date->setTimezone(new \DateTimeZone($this->timezone));
            $date->setTimezone(new \DateTimeZone(self::DEFAULT_TIMEZONE));
            $resultDate = $date;
        }

        //throw exception when wrong date value or format
        if (!$resultDate instanceof DateTime) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE, 0, $this->getField());
        }

        switch ($this->getToDateFormat()) {
            case self::DEFAULT_DATETIME_FORMAT_FULL:
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
            return $value->format($this->getToDateFormatInPHPFormat());
        }

        $date = DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $value);
        if (!$date instanceof DateTime) {
            $date = DateTime::createFromFormat(self::DEFAULT_DATETIME_FORMAT, $value);
        }

        if ($value === '0000-00-00' || !$date instanceof DateTime) {
            return null;
        }

        return $date->format($this->getToDateFormatInPHPFormat());
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
     * @return string
     */
    public function getToDateFormatInPHPFormat()
    {
        return self::convertDateFormatFullToPHPDateFormat($this->toDateFormat);
    }

    /**
     * @param array $fromDateFormats, such as [isCustomFormatDateFrom => true/false, format => '...'] where format is full text such as YYYY/MM/DD, ... , not PHP format (Y/m/d, ...)
     */
    public function setFromDateFormats(array $fromDateFormats)
    {
        $this->fromDateFormats = $fromDateFormats;
    }

    /**
     * @param string $toDateFormat is full text such as YYYY/MM/DD, ... , not PHP format (Y/m/d, ...)
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
     * convert custome DateFormat To PHP format
     * e.g:
     * - YYYY MM, DD => Y m, d
     * - YYYY--MM--DDD => Y--m--D
     * - YYYY/MMM, DD => Y/M, d
     * - ...
     *
     * @param string $dateFormat
     * @return string|bool false if dateFormat is not a string
     */
    public static function convertCustomFromDateFormatToPHPDateFormat($dateFormat)
    {
        if (!is_string($dateFormat)) {
            return false;
        }

        // validate format
        if (!self::validateCustomFullDateFormat($dateFormat)) {
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

        // replacing HH:mm:ss to H:i:s
        $convertedDateFormat = str_replace('HH', 'H', $convertedDateFormat); // hour
        $convertedDateFormat = str_replace('mm', 'i', $convertedDateFormat); // min
        $convertedDateFormat = str_replace('ss', 's', $convertedDateFormat); // sec

        return $convertedDateFormat;
    }

    /**
     * convert full DateFormat To PHP format
     * e.g:
     * - YYYY.MM.DD => Y.m.d
     * - YYYY.MM.DDD => Y.m.D
     * - YYYY.MMM.DD => Y.M.d
     * - ...
     *
     * @param string $dateFormat
     * @return string|bool false if dateFormat is not a string
     */
    public static function convertDateFormatFullToPHPDateFormat($dateFormat)
    {
        if (!is_string($dateFormat)) {
            return false;
        }

        // validate if supported format
        if (!array_key_exists($dateFormat, self::SUPPORTED_DATE_FORMATS)) {
            return false;
        }

        return self::SUPPORTED_DATE_FORMATS[$dateFormat];
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

            if (!$isCustomDateFormat && !array_key_exists($fromFormat, self::SUPPORTED_DATE_FORMATS)) {
                $isSupportDateFormat = false;
                break;
            }

            if ($isCustomDateFormat && !self::validateCustomFullDateFormat($fromFormat)) {
                $isSupportDateFormat = false;
                break;
            }
        }

        if (!$isSupportDateFormat) {
            // validate using builtin data formats (when isCustomFormatDateFrom = false)
            throw  new BadRequestHttpException(sprintf('Transform setting error: field "%s" not support from date format', $this->getField()));
        }

        if (!array_key_exists($this->toDateFormat, self::SUPPORTED_DATE_FORMATS)) {
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
        if (empty($timeZone)) {
            $timeZone = DateFormat::DEFAULT_TIMEZONE;
        }

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
            }

            if (empty($timeZone)) {
                $timeZone = self::DEFAULT_TIMEZONE;
            }

            $fromFormats = $transform[self::FROM_KEY];

            foreach ($fromFormats as $fromFormat) {
                if (!array_key_exists(self::FORMAT_KEY, $fromFormat)) {
                    continue;
                }

                if (array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $fromFormat) && $fromFormat[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM]) {
                    $format = self::convertCustomFromDateFormatToPHPDateFormat($fromFormat[self::FORMAT_KEY]);
                } else {
                    $format = self::convertDateFormatFullToPHPDateFormat($fromFormat[self::FORMAT_KEY]);
                }

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

    /**
     * @param $value
     * @return DateTime
     */
    public function getDateFromText($value)
    {
        foreach (self::SUPPORTED_DATE_FORMATS as $format) {
            $date = date_create_from_format($format, $value);
            if ($date instanceof DateTime) {
                return $date;
            }
        }

        return null;
    }

    /**
     * validate format: allow YY, YYYY, M, MM, MMM, MMMM, D, DD, e, O, P, T and special characters . , - _ / <space>.
     * E.g YYYY.MMM.D is for 2017.02.1; YYYY MMMM, DD is for 2017 February, 19
     *
     * @param $customFullDateFormat
     * @return bool
     */
    public static function validateCustomFullDateFormat($customFullDateFormat)
    {
        return preg_match(self::FULL_DATE_FORMAT_REGEX, $customFullDateFormat, $matches) === 1;
    }
}