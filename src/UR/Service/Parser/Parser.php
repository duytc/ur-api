<?php

namespace UR\Service\Parser;

use UR\Model\Core\ConnectedDataSourceInterface;
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
    public function parse(DataSourceInterface $dataSource, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columnsMapping = $parserConfig->getAllColumnMappings();
        $columnFromMap = array_flip($parserConfig->getAllColumnMappings());

        $fileCols = array_map("strtolower", $dataSource->getColumns());
        $fileCols = array_map("trim", $fileCols);
        $columns = array_intersect($fileCols, $parserConfig->getAllColumnMappings());
        $columns = array_map(function ($fromColumn) use ($columnFromMap) {
            return $columnFromMap[$fromColumn];
        }, $columns);

        $format = [];//format date
        foreach ($parserConfig->getColumnTransforms() as $field => $columnTransform) {
            foreach ($columnTransform as $item) {
                if ($item instanceof DateFormat) {
                    $format[$field] = $item->getFromDateForMat();
                }
            }
        }

        $rows = array_values($dataSource->getRows($format));

        $cur_row = -1;
        foreach ($rows as &$row) {
            $cur_row++;
            $row = array_intersect_key($row, $columns);

            $keys = array_map(function ($key) use ($columns) {
                return $columns[$key];
            }, array_keys($row));

            $row = array_combine($keys, $row);

            foreach ($connectedDataSource->getDataSet()->getMetrics() as $metric => $type) {
                if (array_key_exists($metric, $row)) {
                    if ($type === Type::NUMBER || $type === Type::DECIMAL) {
                        // remove dollar sign if has
                        $row[$metric] = str_replace("$", "", $row[$metric]);

                        // remove thousand separator if has
                        $row[$metric] = str_replace(",", "", $row[$metric]);

                        // remove spaces for decimal if has
                        $row[$metric] = str_replace(" ", "", $row[$metric]);

                        if (!is_numeric($row[$metric])) {
                            // TODO: not alert, only log
                            //return array(ProcessAlert::ERROR => ProcessAlert::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER,
                            //    ProcessAlert::ROW => $cur_row + 2,
                            //    ProcessAlert::COLUMN => $columnsMapping[$metric]);
                        }
                    }
                }
            }

            $isValidFilter = 1;
            foreach ($parserConfig->getColumnFilters() as $column => $filters) {
                /**@var ColumnFilterInterface[] $filters */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($filters as $filter) {
                    $filterResult = $filter->filter($row[$column]);
                    if ($filterResult > 1) {
                        return array(ProcessAlert::ERROR => $filterResult,
                            ProcessAlert::ROW => $cur_row + 2,
                            ProcessAlert::COLUMN => $columnsMapping[$column]);
                    } else {
                        $isValidFilter = $isValidFilter & $filterResult;
                    }
                }
            }

            if (!$isValidFilter) {
                unset($rows[$cur_row]);
                continue;
            }

            foreach ($parserConfig->getColumnTransforms() as $column => $transforms) {
                /** @var ColumnTransformerInterface[] $transforms */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($transforms as $transform) {
                    $row[$column] = $transform->transform($row[$column]);
                    if ($row[$column] === 2) {
                        return array(ProcessAlert::ERROR => ProcessAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE,
                            ProcessAlert::ROW => $cur_row + 2,
                            ProcessAlert::COLUMN => $columnsMapping[$column]);
                    }
                }
            }
        }

        $collection = new Collection(array_values($columns), $rows);

        $allFieldsTransforms = $parserConfig->getCollectionTransforms();

        usort($allFieldsTransforms, function (CollectionTransformerInterface $a, CollectionTransformerInterface $b) {
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        foreach ($allFieldsTransforms as $transform) {
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