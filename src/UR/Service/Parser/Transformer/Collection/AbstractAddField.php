<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

abstract class AbstractAddField extends AbstractTransform implements CollectionTransformerInterface
{
    /**
     * @var string
     */
    protected $column;

    /**
     * AbstractAddField constructor.
     * @param string $column
     * @param $priority
     */
    public function __construct($column, $priority)
    {
        parent::__construct($priority);
        $this->column = $column;
    }

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