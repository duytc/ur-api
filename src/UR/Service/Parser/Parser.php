<?php

namespace UR\Service\Parser;

use UR\Model\Core\DataSetInterface;
use UR\Service\Alert\AlertParams;
use UR\Service\Alert\ProcessAlert;
use UR\Service\DataSet\Type;
use UR\Service\DataSource\DataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class Parser implements ParserInterface
{
    public function parse(DataSourceInterface $dataSource, ParserConfig $config, DataSetInterface $dataSet)
    {
        $columnFromMap = array_flip($config->getAllColumnMappings());

        $fileCols = array_map("strtolower", $dataSource->getColumns());
        $fileCols = array_map("trim", $fileCols);
        $columns = array_intersect($fileCols, $config->getAllColumnMappings());
        $columns = array_map(function ($fromColumn) use ($columnFromMap) {
            return $columnFromMap[$fromColumn];
        }, $columns);

        $format = [];//format date
        foreach ($config->getColumnTransforms() as $field => $columnTransform) {
            foreach ($columnTransform as $item) {
                if ($item instanceof DateFormat) {
                    $format[$field] = $item->getFromDateForMat();
                }
            }
        }

        $rows = $dataSource->getRows($format);

        $cur_row = -1;
        foreach ($rows as &$row) {
            $cur_row++;
            $row = array_intersect_key($row, $columns);

            $keys = array_map(function ($key) use ($columns) {
                return $columns[$key];
            }, array_keys($row));

            $row = array_combine($keys, $row);

            foreach ($dataSet->getMetrics() as $metric => $type) {
                if (array_key_exists($metric, $row)) {
                    if (strcmp($type, Type::NUMBER) === 0) {
                        $row[$metric] = str_replace("$", "", $row[$metric]);
                        $row[$metric] = str_replace(",", "", $row[$metric]);
                    }

                    if (strcmp($type, Type::DECIMAL) === 0) {
                        $row[$metric] = str_replace("$", "", $row[$metric]);
                        $row[$metric] = str_replace(",", "", $row[$metric]);
                        $row[$metric] = str_replace(" ", "", $row[$metric]);
                    }

                    if (strcmp(trim($row[$metric]), "") !== 0 && !is_numeric($row[$metric])) {
                        return array('error' => ProcessAlert::DATA_IMPORT_TRANSFORM_FAIL,
                            'row' => $cur_row + 2,
                            'column' => $metric);
                    }
                }
            }

            $isValidFilter = 1;
            foreach ($config->getColumnFilters() as $column => $filters) {
                /**@var ColumnFilterInterface[] $filters */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($filters as $filter) {
                    $filterResult = $filter->filter($row[$column]);
                    if ($filterResult > 1) {
                        return array('error' => ProcessAlert::DATA_IMPORT_FILTER_FAIL,
                            'row' => $cur_row + 2,
                            'column' => $column);
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
                    if ($row[$column] === 2) {
                        return array('error' => ProcessAlert::DATA_IMPORT_TRANSFORM_FAIL,
                            'row' => $cur_row + 2,
                            'column' => $column);
                    }
                }
            }
        }

        $collection = new Collection(array_values($columns), $rows);

        foreach ($config->getCollectionTransforms() as $transform) {
            /** @var CollectionTransformerInterface $transform */
            try {
                $collection = $transform->transform($collection);
            } catch (\Exception $e) {
                return $collection;
            }
        }

        return $collection;
    }
}