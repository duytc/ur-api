<?php

namespace UR\Bundle\ApiBundle\Event;

class UrParseRowsEvent extends UrEvent
{
    /**
     * @var array
     */
    protected $rows;

    public function __construct($publisherId, $connectedDataSourceId, $dataSourceId, array $rows)
    {
        parent::__construct($publisherId, $connectedDataSourceId, $dataSourceId);
        $this->rows = $rows;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param array $rows
     */
    public function setRows(array $rows)
    {
        $this->rows = $rows;
    }
}