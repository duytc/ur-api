<?php

namespace UR\Service\Parser;

use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;

class ParserConfig
{
    protected $columnMapping = [];
    /**
     * @var ColumnFilterInterface[]
     */
    protected $columnFilters = [];
    /**
     * @var ColumnTransformerInterface[]
     */
    protected $columnTransforms = [];
    /**
     * @var CollectionTransformerInterface[]
     */
    protected $collectionTransforms = [];

    public function addColumn($fromColumn, $toColumn = null)
    {
        if (empty($fromColumn)) {
            throw new \InvalidArgumentException('column names cannot be empty');
        }

        if (empty($toColumn)) {
            $toColumn = $fromColumn;
        }

        if (!preg_match('#[_a-z]+#i', $toColumn)) {
            throw new \InvalidArgumentException('column names can only contain alpha characters and underscores');
        }

        if (in_array($fromColumn, $this->columnMapping, true)) {
            throw new \InvalidArgumentException(sprintf('The column "%s" is already mapped. Column mapping must be unique', $fromColumn));
        }

        if (array_key_exists($toColumn, $this->columnMapping)) {
            throw new \InvalidArgumentException(sprintf('The column "%s" already exists', $toColumn));
        }

        $this->columnMapping[$toColumn] = $fromColumn;

        return $this;
    }

    public function filtersColumn($column, ColumnFilterInterface $filter)
    {
        $this->columnFilters[$column][] = $filter;
    }

    public function transformColumn($column, ColumnTransformerInterface $transform)
    {
        if (!$this->hasColumnMapping($column)) {
            throw new \InvalidArgumentException('Cannot add the column transform because the column does not exist');
        }

        if (!array_key_exists($column, $this->columnTransforms)) {
            $this->columnTransforms[$column] = [];
        }

        $this->columnTransforms[$column][] = $transform;

        return $this;
    }

    public function transformCollection(CollectionTransformerInterface $transform)
    {
        $this->collectionTransforms[] = $transform;

        return $this;
    }

    /**
     * @return array
     */
    public function getAllColumnMappings()
    {
        return $this->columnMapping;
    }

    /**
     * @param string $fromColumn
     * @return bool
     */
    public function hasColumnMapping($fromColumn)
    {
        return array_key_exists($fromColumn, $this->columnMapping);
    }

    /**
     * @param string $fromColumn
     * @return string|false
     */
    public function getColumnMapping($fromColumn)
    {
        if ($this->hasColumnMapping($fromColumn)) {
            return $this->columnMapping[$fromColumn];
        }

        return false;
    }

    /**
     * @return ColumnTransformerInterface[]
     */
    public function getColumnTransforms()
    {
        return $this->columnTransforms;
    }

    /**
     * @return ColumnFilterInterface[]
     */
    public function getColumnFilters()
    {
        return $this->columnFilters;
    }

    /**
     * @return CollectionTransformerInterface[]
     */
    public function getCollectionTransforms()
    {
        return $this->collectionTransforms;
    }
}