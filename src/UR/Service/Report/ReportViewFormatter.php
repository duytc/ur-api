<?php
namespace UR\Service\Report;

use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Formats\ColumnPositionFormatInterface;
use UR\Domain\DTO\Report\Formats\CurrencyFormatInterface;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Report\ReportResultInterface;

class ReportViewFormatter implements ReportViewFormatterInterface
{

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
        $reports = $reportResult->getReports();
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

        if (count($decimalFields) < 1) {
            return $reportResult;
        }

        foreach ($reports as &$report) {
            foreach ($decimalFields as $decimalField => $name) {
                if (!array_key_exists($decimalField, $report)) {
                    continue;
                }
                if ($report[$decimalField] == null) {
                    continue;
                }
                $report[$decimalField] = number_format((float)$report[$decimalField], 4, ".", "");
            }

            foreach ($numberFields as $numberField => $name) {
                if (!array_key_exists($numberField, $report)) {
                    continue;
                }
                if ($report[$numberField] == null) {
                    continue;
                }
                $report[$numberField] = round($report[$numberField]);
            }
        }

        $reportResult->setReports($reports);
        return $reportResult;
    }

    /**
     * @inheritdoc
     */
    public function getSmartColumns($reportResult, $params)
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

        $reportViewAlias = 'report_view_alias';
        if ($params->isMultiView()) {
            $smartColumns[$reportViewAlias] = "Report View Alias";
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
        $reports = $reportResult->getReports();

        foreach ($reports as &$report) {
            $needDeleteFields = array_diff_key($report, $columns);
            foreach ($needDeleteFields as $needDeleteField => $displayName) {
                unset ($report[$needDeleteField]);
            }
        }
        $reportResult->setReports($reports);
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