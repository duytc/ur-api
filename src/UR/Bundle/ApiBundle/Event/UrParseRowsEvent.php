<?php

namespace UR\Bundle\ApiBundle\Event;

use SplDoublyLinkedList;

class UrParseRowsEvent extends UrEvent
{
    /**
     * @var SplDoublyLinkedList
     */
    protected $rows;

    public function __construct($publisherId, $connectedDataSourceId, $dataSourceId, SplDoublyLinkedList $rows)
    {
        parent::__construct($publisherId, $connectedDataSourceId, $dataSourceId);
        $this->rows = $rows;
    }

    /**
     * @return SplDoublyLinkedList
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param SplDoublyLinkedList $rows
     */
    public function setRows($rows)
    {
        $this->rows = $rows;
    }
}