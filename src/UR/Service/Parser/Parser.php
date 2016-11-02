<?php

namespace UR\Service\Parser;

use UR\Service\DataSource\DataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;

class Parser implements ParserInterface
{
    public function parse(DataSourceInterface $dataSource, ParserConfig $config)
    {
        $columnFromMap = array_flip($config->getAllColumnMappings());

        $columns = array_intersect($dataSource->getColumns(), $config->getAllColumnMappings());

        $columns = array_map(function ($fromColumn) use ($columnFromMap) {
            return $columnFromMap[$fromColumn];
        }, $columns);

        $rows = $dataSource->getRows();

        $cur_row = -1;
        foreach ($rows as &$row) {
            $cur_row++;
            $row = array_intersect_key($row, $columns);

            $keys = array_map(function ($key) use ($columns) {
                return $columns[$key];
            }, array_keys($row));

            $row = array_combine($keys, $row);

            $isValidFilter = 1;
            foreach ($config->getColumnFilters() as $column => $filters) {
                /**@var ColumnFilterInterface[] $filters */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($filters as $filter) {
                    $filterResult = $filter->filter($row[$column]);
                    if ($filterResult > 1) {
                        return array("error" => "filter",
                            "row" => $cur_row,
                            "column" => $column);
                    } else {
                        $isValidFilter = $isValidFilter & $filterResult;
                    }
                }
            }

            if (!$isValidFilter) {
                unset($rows[$cur_row]);
                continue;
            }

            foreach ($config->getColumnTransforms() as $column => $transforms) {
                /** @var ColumnTransformerInterface[] $transforms */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($transforms as $transform) {
                    $row[$column] = $transform->transform($row[$column]);
                    if (!$row[$column]) {
                        return array("error" => "transform",
                            "row" => $cur_row,
                            "column" => $column);
                    }
                }
            }
        }

        $collection = new Collection(array_values($columns), $rows);

        foreach ($config->getCollectionTransforms() as $transform) {
            /** @var CollectionTransformerInterface $transform */
            $collection = $transform->transform($collection);
        }

        return $collection;
    }
}