<?php

namespace UR\Service\Parser;

use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\PublicSimpleException;

class ParserConfig
{
    protected $columnMapping = [];
    /**
     * @var ColumnFilterInterface[]
     */
    protected $columnFilters = [];
    /**
     * @var array
     */
    protected $transforms = [];

    /**
     * ParserConfig constructor.
     */
    public function __construct()
    {
        $this->columnMapping = [];
        $this->columnFilters = [];
        $this->transforms = [];
    }

    public function addColumn($fromColumn, $toColumn = null)
    {
        if (empty($fromColumn)) {
            throw new PublicSimpleException('column names cannot be empty');
        }

        if (empty($toColumn)) {
            $toColumn = $fromColumn;
        }

        if (!preg_match('#[_a-z]+#i', $toColumn)) {
            throw new PublicSimpleException('column names can only contain alpha characters and underscores');
        }

        if (in_array($fromColumn, $this->columnMapping, true)) {
            throw new PublicSimpleException(sprintf('The column "%s" is already mapped. Column mapping must be unique', $fromColumn));
        }

        if (array_key_exists($toColumn, $this->columnMapping)) {
            return $this;
        }

        $this->columnMapping[$toColumn] = $fromColumn;

        return $this;
    }

    public function addFiltersColumn($column, ColumnFilterInterface $filter)
    {
        $this->columnFilters[$column][] = $filter;
    }

    /**
     * @return ColumnFilterInterface[]
     */
    public function getColumnFilters()
    {
        return $this->columnFilters;
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
     * @param $transform
     * @return $this
     */
    public function addTransform($transform)
    {
        $this->transforms[] = $transform;

        return $this;
    }

    /**
     * @return array
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @param array
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;
    }
}