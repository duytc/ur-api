<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface ReportScheduleInterface extends ModelInterface
{
    /**
     * @return ReportViewInterface
     */
    public function getReportView();

    /**
     * @param ReportViewInterface $reportView
     * @return self
     */
    public function setReportView($reportView);

    /**
     * @return array
     */
    public function getEmails();

    /**
     * @param array $emails
     * @return self
     */
    public function setEmails($emails);

    /**
     * @return mixed
     */
    public function getSchedule();

    /**
     * @param mixed $schedule
     * @return self
     */
    public function setSchedule($schedule);

    /**
     * @return boolean
     */
    public function isAlertMissingData();

    /**
     * @param boolean $alertMissingData
     * @return self
     */
    public function setAlertMissingData($alertMissingData);
}