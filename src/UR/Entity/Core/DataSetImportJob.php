<?php


namespace UR\Entity\Core;

use UR\Model\Core\DataSetImportJob as DataSetImportJobModel;


class DataSetImportJob extends DataSetImportJobModel
{
    protected $id;
    protected $dataSet;
    protected $jobId;
    protected $createdDate;
}