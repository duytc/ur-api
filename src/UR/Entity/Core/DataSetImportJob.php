<?php


namespace UR\Entity\Core;

use UR\Model\Core\DataSetImportJob as DataSetImportJobModel;


class DataSetImportJob extends DataSetImportJobModel
{
    protected $id;
    protected $dataSet;
    protected $connectedDataSource;
    protected $jobId;
    protected $jobName;
    protected $jobData;
    protected $jobType;
    protected $createdDate;
    protected $waitFor;
}