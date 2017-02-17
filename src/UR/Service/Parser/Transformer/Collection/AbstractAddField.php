<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

abstract class AbstractAddField implements CollectionTransformerInterface
{
    /**
     * @var string
     */
    protected $column;

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        if (count($rows) < 1) {
            return $collection;
        }
        $columns = $collection->getColumns();

        if (in_array($this->column, $columns, true)) {
            return $collection;
        }

        $columns[] = $this->column;

        foreach ($rows as $idx => &$row) {
            $value = $this->getValue($row);
            if (is_array($value)) {
                return $value;
            }
            $row[$this->column] = $value;
        }

        return new Collection($columns, $rows);
    }

    abstract protected function getValue(array $row);
}