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

    /**
     * @var array
     */
    protected $types;

    public function __construct(array $columns, array $rows, $types = [])
    {
        $this->columns = $columns;
        $this->rows = $rows;
        $this->types = $types;
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

    /**
     * @param array $rows
     * @return self
     */
    public function setRows($rows)
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @param $columns
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }

    public function getTypeOf($field)
    {
        if (array_key_exists($field, $this->types)) {
            return $this->types[$field];
        }

        return null;
    }
}