<?php


namespace UR\Domain\DTO\Report;


use DateTime;

class DateRange
{
    /**
     * @var DateTime
     */
    protected $startDate;

    /**
     * @var DateTime
     */
    protected $endDate;

    /**
     * DateRange constructor.
     * @param DateTime $startDate
     * @param $endDate
     */
    public function __construct($startDate, $endDate)
    {
        if ($startDate instanceof DateTime) {
            $this->startDate = $startDate;
        } else {
            $this->startDate = new DateTime($startDate);
        }

        if ($endDate instanceof DateTime) {
            $this->endDate = $endDate;
        } else {
            $this->endDate = new DateTime($endDate);
        }
    }

    /**
     * @return DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'startDate' => $this->startDate->format('Y-m-d'),
            'endDate' => $this->endDate->format('Y-m-d'),
        ];
    }
}