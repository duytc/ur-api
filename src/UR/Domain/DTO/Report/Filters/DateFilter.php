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
    protected $startDate;

    protected $endDate;

    /**
     * DateFilter constructor.
     * @param string $fieldName
     * @param int $fieldType
     * @param string $dateFormat
     * @param $startDate
     * @param $endDate
     */
    public function __construct($fieldName, $fieldType, $dateFormat, $startDate, $endDate)
    {
        $this->fieldName = $fieldName;
        $this->fieldType = $fieldType;
        $this->dateFormat = $dateFormat;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
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