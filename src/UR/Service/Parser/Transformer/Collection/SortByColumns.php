<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

class SortByColumns implements CollectionTransformerInterface
{
    protected $sortByColumns;

    public function __construct(array $sortByColumns)
    {
        $this->sortByColumns = $sortByColumns;
    }

    public function transform(Collection $collection)
    {
        $columns = $collection->getColumns();
        $rows = $collection->getRows();

        $sortByColumns = array_intersect($columns, $this->sortByColumns);

        if (count($sortByColumns) != count($this->sortByColumns)) {
            throw new \InvalidArgumentException('Cannot sort the collection, some of the columns do not exist');
        }

        // todo implement sorting
        foreach ($sortByColumns as $sortByColumn) {
            $this->array_sort_by_column($rows, $sortByColumn);
        }

        return new Collection($columns, $rows);
    }

    public function getPriority()
    {
        return 0;
    }

    public function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
        $sort_col = array();
        foreach ($arr as $key=> $row) {
            $sort_col[$key] = $row[$col];
        }

        array_multisort($sort_col, $dir, $arr);
    }
}