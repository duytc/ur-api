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
        $columns = $collection->getColumns();
        $rows = $collection->getRows();

        $columnCheck = array_diff([$this->columnA, $this->columnB], $columns);

        if (count($columnCheck) > 0) {
            $columns[]=$this->newColumn;
            foreach ($rows as $idx => &$row) {
                $value = "";
                $row[$this->newColumn] = $value;
            }

            return new Collection($columns, $rows);

//            throw new \InvalidArgumentException('Some of the expected columns do not exist');
        }

        if (in_array($this->newColumn, $columns, true)) {
            throw new \InvalidArgumentException('Cannot add calculated column, it already exists');
        }

        $columns[] = $this->newColumn;

        foreach ($rows as &$row) {
            $value = null;

            if ($row[$this->columnB] > 0) {
                $value = abs(($row[$this->columnA] - $row[$this->columnB]) / $row[$this->columnB]);
            }

            $row[$this->newColumn] = $value;
        }

        return new Collection($columns, $rows);
    }

    public function getPriority()
    {
        return 1;
    }
}