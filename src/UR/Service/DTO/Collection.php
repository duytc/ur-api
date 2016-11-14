<?php

namespace UR\Service\DTO;

class Collection
{
    /**
     * @var array
     */
    protected $columns;

    /**
     * @var array
     */
    protected $rows;

    public function __construct(array $columns, array $rows)
    {
        $this->columns = $columns;
        $this->rows = $rows;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }


    public function addColumn($column)
    {
        if (!is_array($this->columns)) {
            $this->columns = [];
        }

        $this->columns[] = $column;
        return $this;
    }
}