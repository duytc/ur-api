<?php


namespace UR\Entity\Core;

use UR\Model\Core\LinkedMapDataSet as LinkedMapDataSetModel;


class LinkedMapDataSet extends LinkedMapDataSetModel
{
    protected $id;
    protected $connectedDataSource;
    protected $mapDataSet;
    protected $mappedFields;
}