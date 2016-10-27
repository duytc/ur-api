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
        $columns = $collection->getColumns();
        $rows = $collection->getRows();

        if (in_array($this->column, $columns, true)) {
            throw new \RuntimeException(sprintf('column "%s" already exists so it cannot be added', $this->column));
        }

        $columns[] = $this->column;

        foreach ($rows as $idx => &$row) {
            $value = $this->getValue($row);
            $row[$this->column] = $value;
        }

        return new Collection($columns, $rows);
    }

    abstract protected function getValue(array $row);

    public function getPriority()
    {
        return 0;
    }
}