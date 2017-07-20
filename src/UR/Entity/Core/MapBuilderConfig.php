<?php


namespace UR\Entity\Core;

use \UR\Model\Core\MapBuilderConfig as MapBuilderConfigModel;
class MapBuilderConfig extends MapBuilderConfigModel
{
    protected $id;
    protected $name;
    protected $mapDataSet;
    protected $mapFields;
    protected $dataSet;
    protected $filters;
    protected $leftSide;
}