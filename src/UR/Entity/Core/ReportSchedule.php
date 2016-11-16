<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportSchedule as ReportScheduleModel;
class ReportSchedule extends ReportScheduleModel
{
    protected $id;
    protected $alertMissingData;
    protected $emails;
    protected $reportView;
    protected $schedule;
}