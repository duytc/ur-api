<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

class ComparisonPercent implements CollectionTransformerInterface
{
    protected $newColumn;
    protected $columnA;
    protected $columnB;

    public function __construct($newColumn, $columnA, $columnB)
    {
        $this->newColumn = $newColumn;
        $this->columnA = $columnA;
        $this->columnB = $columnB;
    }

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        if (count($rows) < 1) {
            return $collection;
        }

        $columns = $collection->getColumns();
        $columnCheck = [];
        foreach ($rows as $row) {
            $columnCheck = array_diff([$this->columnA, $this->columnB], array_keys($row));
            break;
        }

        if (count($columnCheck) > 0) {
            $columns[] = $this->newColumn;
            foreach ($rows as $idx => &$row) {
                $value = "";
                $row[$this->newColumn] = $value;
            }

            return new Collection($columns, $rows);
        }

        if (in_array($this->newColumn, $collection->getColumns(), true)) {
            throw new \InvalidArgumentException('Cannot add calculated column, it already exists');
        }

        $columns[] = $this->newColumn;

        foreach ($rows as &$row) {
            $value = null;
            if (!is_numeric($row[$this->columnA]) || !is_numeric($row[$this->columnB])) {
                $row[$this->newColumn] = $value;
                continue;
            }

            if ($row[$this->columnB] > 0) {
                $value = abs(($row[$this->columnA] - $row[$this->columnB]) / $row[$this->columnB]);
            }

            $row[$this->newColumn] = $value;
        }

        return new Collection($columns, $rows);
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::TRANSFORM_PRIORITY_COMPARISON_PERCENT;
    }
}