<?php


namespace UR\Domain\DTO\Report\Filters;


class DateFilter extends AbstractFilter implements DateFilterInterface
{
    /**
     * @var string
     */
    protected $dateFormat;

    /**
     * @var array
     */
    protected $dateRange;

    /**
     * DateFilter constructor.
     * @param string $fieldName
     * @param int $fieldType
     * @param string $dateFormat
     * @param array $dateRange
     */
    public function __construct($fieldName, $fieldType, $dateFormat, array $dateRange)
    {
        $this->fieldName = $fieldName;
        $this->fieldType = $fieldType;
        $this->dateFormat = $dateFormat;
        $this->dateRange = $dateRange;
    }

    /**
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * @return array
     */
    public function getDateRange()
    {
        return $this->dateRange;
    }
}