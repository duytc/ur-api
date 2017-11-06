<?php

namespace UR\Service\Parser\Filter;


use DateTime;
use Exception;
use UR\Model\Core\AlertInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Transformer\Column\DateFormat;

class DateFilter extends AbstractFilter implements ColumnFilterInterface
{
    const FORMAT_FILTER_KEY = 'format';
    const TIMEZONE_KEY = 'timezone';
    const FORMATS_FILTER_KEY = 'formats';
    const START_DATE_FILTER_KEY = 'startDate';
    const END_DATE_FILTER_KEY = 'endDate';
    const IS_PARTIAL_MATCH_KEY = 'isPartialMatch';
    // Resets all fields (year, month, day, hour, minute, second, fraction and timezone information) to the Unix Epoch. Without !, all fields will be set to the current date and time.
    const DEFAULT_DATE_FORMAT = '!Y-m-d';

    /** @var DateTime */
    protected $startDate;

    /** @var DateTime */
    protected $endDate;

    /** @var array */
    protected $format;

    /** @var DateFormat */
    protected $transform;

    /**
     * DateFilter constructor.
     * @param string $field
     * @param string $startDateString
     * @param string $endDateString
     * @param array $formats
     * @param string $timezone
     * @throws Exception
     */
    public function __construct($field, $startDateString, $endDateString, array $formats, $timezone = 'UTC')
    {
        parent::__construct($field);

        $this->startDate = DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $startDateString);
        $this->endDate = DateTime::createFromFormat(self::DEFAULT_DATE_FORMAT, $endDateString);

        // notice: reset time for startDate to start of day, endDate to end of day
        // this corrects the comparison for datetime Y-m-d H:i:s
        // e.g: endDate = 2017-06-23, if input is 2017-06-23 14:42:00
        // if not reset to end of day => input is filtered out
        // if fix by resetting to end of day (2017-06-24 23:59:59) => input is valid

        if ($this->startDate instanceof DateTime) {
            $this->startDate->setTime(0, 0, 0);
        }

        if ($this->endDate instanceof DateTime) {
            $this->endDate->setTime(23, 59, 59);
        }


        $this->transform = new DateFormat($field, $formats, $timezone, 'Y-m-d H:i:s');
    }

    /**
     * @param $value
     * @return bool
     * @throws ImportDataException
     */
    public function filter($value)
    {
        // support partial match value
        try {
            $date  = $this->transform->getDate($value);
        } catch (ImportDataException $ex) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_DATE, 0, $this->getField());
        }

        if (empty($date)) {
            return false;
        }

        if ($date <= $this->endDate && $date >= $this->startDate) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws Exception
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

    /**
     * @return DateFormat
     */
    public function getTransform(): DateFormat
    {
        return $this->transform;
    }

    /**
     * @param DateFormat $transform
     * @return self
     */
    public function setTransform($transform)
    {
        $this->transform = $transform;
        return $this;
    }

    /**
     * getPartialMatchValue
     *
     * TODO: support custom format date
     * TODO: if above support custom format date, alter supported format from php format (Y-m-d ...) to full text (YYYY-MM-DD ...),
     * TODO: then use UR/Service/Parser/Transformer/Column/DateFormat::getPartialMatchValue
     *
     * e.g:
     * - dateFormat = Y-m-d and value = 2017-06-23 14:02:00+0000 => partial match value = 2017-06-23
     * - dateFormat = Y-m-d H:i:s and value = 2017-06-23 14:02:00+0000 => partial match value = 2017-06-23 14:02:00
     *
     * @param $dateFormat
     * @param $value
     * @return mixed|string original value if input invalid, null if not matched, else return the partialMatchedValue
     */
    public static function getPartialMatchValue($dateFormat, $value)
    {
        if (!is_string($dateFormat) || !is_string($value)) {
            return $value;
        }

        $partialMatchedValue = preg_replace('/^(' . self::getPartialMatchPatternFromDateFormat($dateFormat) .')(.*)$/', '\1', $value);

        return (!$partialMatchedValue) ? null : $partialMatchedValue;
    }

    /**
     * get partial match pattern from date format
     * e.g:
     * - Y-m-d H:i:s P => \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [+\-]?\d{2}:\d{2}
     *
     * @param string $dateFormat
     * @return string|bool false if dateFormat is not a string
     */
    public static function getPartialMatchPatternFromDateFormat($dateFormat)
    {
        if (!is_string($dateFormat)) {
            return false;
        }

        $convertedPartialMatch = preg_quote($dateFormat, "/"); // these are supported characters in custom format

        // important: change "d" to avoid conflict with "d" in replaced value "\d{...}"
        $convertedPartialMatch = str_replace('d', '$$DD$$', $convertedPartialMatch);

        $convertedPartialMatch = str_replace('Y', '\d{4}', $convertedPartialMatch); // 4 digits
        $convertedPartialMatch = str_replace('y', '\d{2}', $convertedPartialMatch); // 2 digits

        $convertedPartialMatch = str_replace('F', '[a-zA-Z]{4}', $convertedPartialMatch); // full name
        $convertedPartialMatch = str_replace('M', '[a-zA-Z]{3}', $convertedPartialMatch); // 3 characters
        $convertedPartialMatch = str_replace('m', '\d{2}', $convertedPartialMatch); // 2 characters
        $convertedPartialMatch = str_replace('n', '\d{1}', $convertedPartialMatch); // 1 character without leading zeros

        // do for date "d"
        $convertedPartialMatch = str_replace('$$DD$$', '\d{2}', $convertedPartialMatch); // 2 characters
        $convertedPartialMatch = str_replace('j', '\d{1}', $convertedPartialMatch); // 1 character without leading zeros

        // replacing HH:mm:ss to H:i:s
        $convertedPartialMatch = str_replace('H', '\d{2}', $convertedPartialMatch); // hour
        $convertedPartialMatch = str_replace('i', '\d{2}', $convertedPartialMatch); // min
        $convertedPartialMatch = str_replace('s', '\d{2}', $convertedPartialMatch); // sec

        $convertedPartialMatch = str_replace('e', '\w', $convertedPartialMatch); // PST
        $convertedPartialMatch = str_replace('O', '[+\-]?\d{4}', $convertedPartialMatch); // +0000
        $convertedPartialMatch = str_replace('P', '[+\-]?\d{2}:\d{2}', $convertedPartialMatch); // +00:00
        $convertedPartialMatch = str_replace('T', '\w', $convertedPartialMatch); // GMT

        // trim space at the end
        $convertedPartialMatch = trim($convertedPartialMatch);

        return $convertedPartialMatch;
    }
}