<?php

namespace UR\Service\DataSource;

class Collection implements DataSourceInterface
{
    protected $columns = [];
    protected $rows = [];

    public function __construct(array $rows)
    {
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);

            // need to have the array keys of columns match the array keys of the rows for the parser to work
            $this->columns = array_combine($columns, $columns);
        }

        $this->rows = $rows;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getRows()
    {
        return $this->rows;
    }

    public static function fromDTO(\UR\Service\DTO\Collection $collection)
    {
        return new static($collection->getRows());
    }
}