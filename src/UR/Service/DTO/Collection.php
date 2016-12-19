<?php

namespace UR\Service\DTO;

use UR\Exception\InvalidArgumentException;

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

    protected $addedFields;

    public function __construct(array $columns, array $rows, $types = [], $addedFields = [])
    {
        $this->columns = $columns;
        $this->rows = $rows;

        if (!is_array($types)) {
            throw new InvalidArgumentException(sprintf('expect array, got %s', $types));
        }
        $this->types = $types;

        $this->addedFields = $addedFields;
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

    /**
     * @return array
     */
    public function getAddedFields()
    {
        return $this->addedFields;
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function addField($field, $value)
    {
        if (!is_array($this->addedFields)) {
            $this->addedFields = [];
        }

        $this->addedFields[$field] = $value;
        return $this;
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

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param array $types
     * @return self
     */
    public function setTypes($types)
    {
        $this->types = $types;
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