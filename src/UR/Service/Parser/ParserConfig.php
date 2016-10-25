<?php

namespace UR\Service\Parser;

use UnifiedReports\Parser\Transformer\Column\ColumnTransformerInterface;
use UnifiedReports\Parser\Transformer\Collection\CollectionTransformerInterface;

class ParserConfig
{
    protected $columnMapping = [];
    /**
     * @var ColumnTransformerInterface[]
     */
    protected $columnTransforms = [];
    /**
     * @var CollectionTransformerInterface[]
     */
    protected $collectionTransforms = [];

    public function addColumn(string $fromColumn, string $toColumn = null): self
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

    public function transformColumn($column, ColumnTransformerInterface $transform): self
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

    public function transformCollection(CollectionTransformerInterface $transform): self
    {
        $this->collectionTransforms[] = $transform;

        return $this;
    }

    /**
     * @return array
     */
    public function getAllColumnMappings(): array
    {
        return $this->columnMapping;
    }

    /**
     * @param string $fromColumn
     * @return bool
     */
    public function hasColumnMapping(string $fromColumn): bool
    {
        return array_key_exists($fromColumn, $this->columnMapping);
    }

    /**
     * @param string $fromColumn
     * @return string|false
     */
    public function getColumnMapping(string $fromColumn)
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
     * @return CollectionTransformerInterface[]
     */
    public function getCollectionTransforms()
    {
        return $this->collectionTransforms;
    }
}