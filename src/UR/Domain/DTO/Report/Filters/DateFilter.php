<?php


namespace UR\Domain\DTO\Report\Filters;


use Symfony\Component\Config\Definition\Exception\Exception;

class DateFilter extends AbstractFilter implements DateFilterInterface
{
    const FIELD_TYPE_FILTER_KEY = 'type';
    const FILED_NAME_FILTER_KEY = 'field';
    const DATE_FORMAT_FILTER_KEY = 'format';
    const START_DATE_FILTER_KEY = 'startDate';
    const END_DATE_FILTER_KEY = 'endDate';

    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * @var array
     */
    protected $startDate;

    protected $endDate;

    /**
     * @param array $dateFilter
     */

    public function __construct(array $dateFilter)
    {
        if (!array_key_exists(self::FIELD_TYPE_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::FILED_NAME_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::DATE_FORMAT_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::START_DATE_FILTER_KEY, $dateFilter)
            || !array_key_exists(self::END_DATE_FILTER_KEY, $dateFilter)
        ) {
            throw new Exception (sprintf('Either parameters: %s, %s, %s, %s, %s not exits in date filter', self::FIELD_TYPE_FILTER_KEY, self::FILED_NAME_FILTER_KEY,
                self::DATE_FORMAT_FILTER_KEY, self::START_DATE_FILTER_KEY, self::END_DATE_FILTER_KEY));
        }

        $this->fieldName = $dateFilter[self::FILED_NAME_FILTER_KEY];
        $this->fieldType = $dateFilter[self::FIELD_TYPE_FILTER_KEY];
        $this->dateFormat = $dateFilter[self::DATE_FORMAT_FILTER_KEY];
        $this->startDate = $dateFilter[self::START_DATE_FILTER_KEY];
        $this->endDate = $dateFilter[self::END_DATE_FILTER_KEY];
    }

    /**
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @return array
     */
    public function getStartDate()
    {
        return $this->startDate;
    }
}