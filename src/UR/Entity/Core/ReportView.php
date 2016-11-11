<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportView as ReportViewModel;
class ReportView extends ReportViewModel
{
    protected $id;
    protected $dataSets;
    protected $joinedFields;
    protected $name;
    protected $transforms;
    protected $createdDate;
}