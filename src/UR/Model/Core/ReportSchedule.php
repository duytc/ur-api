<?php


namespace UR\Model\Core;


class ReportSchedule implements ReportScheduleInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var ReportViewInterface
     */
    protected $reportView;

    /**
     * @var array
     */
    protected $emails;

    /**
     * @var
     */
    protected $schedule;

    /**
     * @var boolean
     */
    protected $alertMissingData;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ReportViewInterface
     */
    public function getReportView()
    {
        return $this->reportView;
    }

    /**
     * @param ReportViewInterface $reportView
     * @return self
     */
    public function setReportView($reportView)
    {
        $this->reportView = $reportView;
        return $this;
    }

    /**
     * @return array
     */
    public function getEmails()
    {
        return $this->emails;
    }

    /**
     * @param array $emails
     * @return self
     */
    public function setEmails($emails)
    {
        $this->emails = $emails;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * @param mixed $schedule
     * @return self
     */
    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isAlertMissingData()
    {
        return $this->alertMissingData;
    }

    /**
     * @param boolean $alertMissingData
     * @return self
     */
    public function setAlertMissingData($alertMissingData)
    {
        $this->alertMissingData = $alertMissingData;
        return $this;
    }
}