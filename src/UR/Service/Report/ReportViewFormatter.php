<?php
namespace UR\Service\Report;

use SplDoublyLinkedList;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Formats\ColumnPositionFormatInterface;
use UR\Domain\DTO\Report\Formats\CurrencyFormatInterface;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportViews\ReportView;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Report\ReportResultInterface;

class ReportViewFormatter implements ReportViewFormatterInterface
{
    const REPORT_VIEW_ALIAS_KEY = 'report_view_alias';
    const REPORT_VIEW_ALIAS_NAME = 'Report View Alias';

    /**
     * @inheritdoc
     */
    public function formatReports($reportResult, $formats, $metrics, $dimensions)
    {
        // sort format by priority
        usort($formats, function (FormatInterface $a, FormatInterface $b) {
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        foreach ($formats as $format) {
            if (!($format instanceof FormatInterface)) {
                continue;
            }

            if ($format instanceof ColumnPositionFormatInterface) {
                continue;
            }

            $format->format($reportResult, $metrics, $dimensions);
        }
    }

    /**
     * @inheritdoc
     */
    public function getReportAfterApplyDefaultFormat($reportResult, $params)
    {
        $rows = $reportResult->getRows();
        $total = $reportResult->getTotal();
        $average = $reportResult->getAverage();
        $columns = $reportResult->getColumns();
        $types = $reportResult->getTypes();

        $decimalFields = [];
        $numberFields = [];

        foreach ($columns as $key => $column) {
            if (array_key_exists($key, $types) && $types[$key] == FieldType::DECIMAL) {
                $decimalFields[$key] = $column;
            }

            if (array_key_exists($key, $types) && $types[$key] == FieldType::NUMBER) {
                $numberFields[$key] = $column;
            }
        }

        $formatFields = [];
        $formats = $params->getFormats();
        if (is_array($formats)) {
            foreach ($formats as $format) {
                if ($format instanceof ColumnPositionFormatInterface) {
                    continue;
                }

                if ($format instanceof CurrencyFormatInterface) {
                    continue;
                }

                $formatFields = array_merge($formatFields, $format->getFields());
            }
        }

        foreach ($formatFields as $formatField) {
            if (array_key_exists($formatField, $decimalFields)) {
                unset ($decimalFields[$formatField]);
            }
            if (array_key_exists($formatField, $numberFields)) {
                unset ($numberFields[$formatField]);
            }
        }

        if (count($decimalFields) + count($numberFields) < 1) {
            return $reportResult;
        }

        gc_enable();
        $newRows = new SplDoublyLinkedList();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            foreach ($decimalFields as $decimalField => $name) {
                if (!array_key_exists($decimalField, $row)) continue;

                if ($row[$decimalField] == null) continue;

                $row[$decimalField] = number_format((float)$row[$decimalField], 4, ".", "");
            }

            foreach ($numberFields as $numberField => $name) {
                if (!array_key_exists($numberField, $row)) continue;

                if ($row[$numberField] == null) continue;

                $row[$numberField] = round($row[$numberField]);
            }

            $newRows->push($row);
            unset($row);
        }

        foreach ($total as $key => $value) {
            if (isset($decimalFields[$key])) {
                $total[$key] = number_format((float)$value, 4, ".", "");
                continue;
            }

            if (isset($numberFields[$key])) {
                $total[$key] = round($value);
                continue;
            }
        }
        unset($key, $value);

        foreach ($average as $key => $value) {
            if (isset($decimalFields[$key])) {
                $average[$key] = number_format((float)$value, 4, ".", "");
                continue;
            }

            if (isset($numberFields[$key])) {
                $average[$key] = round($value);
                continue;
            }
        }

        unset($key, $value);
        unset($rows, $row);
        gc_collect_cycles();
        $reportResult->setTotal($total);
        $reportResult->setAverage($average);
        $reportResult->setRows($newRows);
        return $reportResult;
    }

    /**
     * @inheritdoc
     */
    public function getSmartColumns($reportResult, $params, $newFieldsTransform = [])
    {
        $columns = $reportResult->getColumns();
        $types = $reportResult->getTypes();

        $dimensions = [];
        $metrics = [];

        /**
         * Get dimensions and metrics with suffix dataSet id,
         * Example text_1 mean in dataSet 1 have column text
         */
        $dataSets = $params->getDataSets();

        if (count($params->getUserDefinedDimensions()) > 0 || count($params->getUserDefinedMetrics()) > 0) {
            $dimensions = array_unique(array_merge($dimensions, $params->getUserDefinedDimensions()));
            $metrics = array_unique(array_merge($metrics, $params->getUserDefinedMetrics()));
        } elseif ($params->isMultiView()) {
            $dimensions = $params->getDimensions();
            $metrics = $params->getMetrics();

            if (empty($dimensions) && empty($metrics)) {
                $dimensions = [];
                $metrics = [];
                $reportViews = $params->getReportViews();

                foreach ($reportViews as $reportView) {
                    if (!$reportView instanceof ReportView) {
                        continue;
                    }
                    $dimensions = array_merge($dimensions, $reportView->getDimensions());
                    $metrics = array_merge($metrics, $reportView->getMetrics());
                }
            }
        } else {
            foreach ($dataSets as $dataSet) {
                $dimensions = array_merge($dimensions, array_map(function ($item) use ($dataSet) {
                    /** @var DataSetInterface $dataSet */
                    return sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }, $dataSet->getDimensions()));

                $metrics = array_merge($metrics, array_map(function ($item) use ($dataSet) {
                    /** @var DataSetInterface $dataSet */
                    return sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }, $dataSet->getMetrics()));
            }

            $joinFields = $this->getJoinFieldsFromParams($params);
            $dimensions = array_merge($dimensions, $joinFields);

            $calculatedFields = array_diff_key($columns, array_flip(array_merge($dimensions, $metrics)));
            if (count($dimensions) == 0 && count($metrics) == 0) {
                uasort($calculatedFields, array($this, 'compareFieldsWithOutDataSetId'));
                $reportResult->setColumns($calculatedFields);
                return $reportResult;
            }

            $calculatedFields = array_flip($calculatedFields);
            $metrics = array_merge($metrics, $calculatedFields);
        }

        $columnsPositionFormatFields = [];
        $formats = $params->getFormats();
        if (is_array($formats)) {
            $formats = array_filter($params->getFormats(), function ($format) {
                return $format instanceof ColumnPositionFormatInterface;
            });
            foreach ($formats as $format) {
                /** @var ColumnPositionFormatInterface $format */
                $columnsPositionFormatFields = array_merge($columnsPositionFormatFields, $format->getFields());
            }
            $columnsPositionFormatFields = array_filter($columnsPositionFormatFields, function ($field) use ($dimensions, $metrics) {
                if (in_array($field, $dimensions)) {
                    return true;
                }
                if (in_array($field, $metrics)) {
                    return true;
                }
                return false;
            });
        }

        if (!is_array($dimensions)) {
            $dimensions = [];
        }

        if (!is_array($metrics)) {
            $metrics = [];
        }

        if (!is_array($columnsPositionFormatFields)) {
            $columnsPositionFormatFields = [];
        }

        $dimensions = array_diff($dimensions, $columnsPositionFormatFields);
        $metrics = array_diff($metrics, array_flip($columnsPositionFormatFields));

        $dateDimensions = array_filter($dimensions, function ($dimension) use ($types) {
            return array_key_exists($dimension, $types) && ($types[$dimension] == FieldType::DATE || $types[$dimension] == FieldType::DATETIME);
        });
        usort($dateDimensions, array($this, 'compareFieldsWithOutDataSetId'));

        $alphabetDimensions = array_diff($dimensions, $dateDimensions);
        usort($alphabetDimensions, array($this, 'compareFieldsWithOutDataSetId'));
        $alphabetDimensions = array_values($alphabetDimensions);

        $dateMetrics = array_filter($metrics, function ($metric) use ($types) {
            return array_key_exists($metric, $types) && ($types[$metric] == FieldType::DATE || $types[$metric] == FieldType::DATE);
        });
        $alphabetMetrics = array_diff($metrics, $dateMetrics);
        $alphabetMetrics = array_merge($alphabetMetrics, $newFieldsTransform);
        usort($alphabetMetrics, array($this, 'compareFieldsWithOutDataSetId'));
        $alphabetMetrics = array_values($alphabetMetrics);

        /**
         * Order
         *      - dimensions first
         *          - date, datetime
         *          - sort the remaining alphabetically
         *      - metrics
         *          - date, datetime
         *          - sort the remaining alphabetically
         */
        $smartColumns = [];

        $reportViewAlias = self::REPORT_VIEW_ALIAS_KEY;
        if ($params->isMultiView()) {
            $smartColumns[$reportViewAlias] = self::REPORT_VIEW_ALIAS_NAME;
            if ($params->getUserDefinedDimensions() != null && array_key_exists($reportViewAlias, $params->getUserDefinedDimensions())) {
                unset($params->getUserDefinedDimensions()[$reportViewAlias]);
            }
            if ($params->getUserDefinedMetrics() != null && array_key_exists($reportViewAlias, $params->getUserDefinedMetrics())) {
                unset($params->getUserDefinedMetrics()[$reportViewAlias]);
            }
        }

        foreach ($columnsPositionFormatFields as $field) {
            if (array_key_exists($field, $columns)) {
                $smartColumns[$field] = $columns[$field];
            }
        }

        foreach ($dateDimensions as $dimension) {
            if (array_key_exists($dimension, $columns)) {
                $smartColumns[$dimension] = $columns[$dimension];
            }
        }

        foreach ($alphabetDimensions as $dimension) {
            if (array_key_exists($dimension, $columns)) {
                $smartColumns[$dimension] = $columns[$dimension];
            }
        }

        foreach ($dateMetrics as $metric) {
            if (array_key_exists($metric, $columns)) {
                $smartColumns[$metric] = $columns[$metric];
            }
        }

        foreach ($alphabetMetrics as $metric) {
            if (array_key_exists($metric, $columns)) {
                $smartColumns[$metric] = $columns[$metric];
            }
        }

        $reportResult->setColumns($smartColumns);
        $reportResult = $this->syncReportsWithSmartColumns($reportResult);

        return $reportResult;
    }

    /**
     * @param ParamsInterface $params
     * @return mixed
     */
    private function getJoinFieldsFromParams($params)
    {
        $joinConfigs = $params->getJoinConfigs();
        $joinFields = [];
        if (is_array($joinConfigs)) {
            foreach ($joinConfigs as $joinConfig) {
                if (!$joinConfig instanceof JoinConfigInterface) {
                    continue;
                }
                $joinFields[] = $joinConfig->getOutputField();
            }
        }
        return $joinFields;
    }

    /**
     * @param ReportResultInterface $reportResult
     * @return ReportResultInterface
     */
    private function syncReportsWithSmartColumns($reportResult)
    {
        $columns = $reportResult->getColumns();
        $rows = $reportResult->getRows();

        gc_enable();
        $newRows = new SplDoublyLinkedList();
        $rows->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO | SplDoublyLinkedList::IT_MODE_DELETE);
        foreach ($rows as $row) {
            $needDeleteFields = array_diff_key($row, $columns);
            $row = array_diff_key($row, $needDeleteFields);
            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        gc_collect_cycles();
        $reportResult->setRows($newRows);
        return $reportResult;
    }

    /**
     * @param $first
     * @param $second
     * @return int
     */
    private function compareFieldsWithOutDataSetId($first, $second)
    {
        $firstField = null;
        $firstDataSet = null;
        $secondField = null;
        $secondDataSet = null;

        if (preg_match('/([a-zA-Z0-9 _]*)(_)([0-9]+)/', $first, $matches)) {
            $firstField = $matches[1];
            $firstDataSet = $matches[3];
        }

        if (preg_match('/([a-zA-Z0-9 _]*)(_)([0-9]+)/', $second, $matches)) {
            $secondField = $matches[1];
            $secondDataSet = $matches[3];
        }

        if ($firstField == null || $secondField == null) {
            return $this->compare($first, $second);
        }

        if ($this->compare($firstField, $secondField) != 0) {
            return $this->compare($firstField, $secondField);
        }

        return $this->compare($firstDataSet, $secondDataSet);
    }

    /**
     * @param $first
     * @param $second
     * @return int
     */
    private function compare($first, $second)
    {
        if ($first == $second) {
            return 0;
        }
        return ($first < $second) ? -1 : 1;
    }
}