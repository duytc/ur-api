<?php

namespace UR\Service\OptimizationRule;

use DateTime;
use SplDoublyLinkedList;
use UR\Behaviors\OptimizationRuleUtilTrait;
use UR\Domain\DTO\Report\Formats\DateFormatInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DateUtil;
use UR\Service\DateUtilInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\OptimizationRule\Normalization\NormalizerInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\Report\ReportBuilderInterface;

class DataTrainingCollector implements DataTrainingCollectorInterface
{
    use OptimizationRuleUtilTrait;

    /** @var ParamsBuilderInterface */
    private $paramsBuilder;

    /** @var ReportBuilderInterface */
    private $reportBuilder;

    /** @var DataTrainingTableServiceInterface */
    private $dataTrainingTableService;

    private $normalizers;

    /**
     * DataTrainingCollector constructor.
     * @param ParamsBuilderInterface $paramsBuilder
     * @param ReportBuilderInterface $reportBuilder
     * @param DataTrainingTableServiceInterface $dataTrainingTableService
     * @param $normalizers
     */
    public function __construct(ParamsBuilderInterface $paramsBuilder, ReportBuilderInterface $reportBuilder, DataTrainingTableServiceInterface $dataTrainingTableService, $normalizers)
    {
        $this->paramsBuilder = $paramsBuilder;
        $this->reportBuilder = $reportBuilder;
        $this->dataTrainingTableService = $dataTrainingTableService;
        $this->normalizers = $normalizers;
    }

    /**
     * @inheritdoc
     */
    public function buildDataForOptimizationRule(OptimizationRuleInterface $optimizationRule)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $optimizationRule->getReportView();
        $reportViewParams = $this->paramsBuilder->buildFromReportView($reportView);
        $reportViewParams->setPage(null);
        $reportViewParams->setFormats([]);

        try {
            $reportViewData = $this->reportBuilder->getReport($reportViewParams);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }

        $dynamicDateRange = $optimizationRule->getDateRange();
        $dateUtil = new DateUtil();
        $dateRange = $dateUtil->getDynamicDateRange($dynamicDateRange);

        $optimizationRuleData = $this->filterDataByDateRange($reportViewData, $optimizationRule, $dateRange[DateUtilInterface::START_DATE_KEY], $dateRange[DateUtilInterface::END_DATE_KEY]);

        if (!$optimizationRuleData instanceof ReportResultInterface || count($optimizationRuleData->getRows()) < 1) {
            return $optimizationRuleData;
        }

        $result = $this->addIdentifiersToTrainingData($optimizationRuleData, $optimizationRule);

        $result = $this->groupAndGenerateGlobalTrainingData($result, $optimizationRule);

        $result = $this->normalizeSegments($result, $optimizationRule);

        return $result;
    }

    private function filterDataByDateRange(ReportResultInterface $reportResult, OptimizationRuleInterface $optimizationRule, $startDate, $endDate)
    {
        $dateField = $optimizationRule->getDateField();
        if (empty($startDate) || empty($endDate)) {
            return $reportResult;
        }

        $startDate = date_create($startDate);
        $endDate = date_create($endDate);

        /** @var SplDoublyLinkedList $rows */
        $rows = $reportResult->getRows();
        $newRows = new \SplDoublyLinkedList();
        foreach ($rows as $index => $row) {
            if (!array_key_exists($dateField, $row)) {
                continue;
            }

            if ($row[$dateField] instanceof DateTime) {
                $date = $row[$dateField];
            } else {
                $date = date_create($row[$dateField]);
            }

            if ($startDate <= $date && $date <= $endDate) {
                $row[$dateField] = $date->format(DateFormatInterface::DEFAULT_DATE_FORMAT);
                $newRows->push($row);
            }
        }
        $reportResult->setRows($newRows);

        return $reportResult;
    }

    /**
     * @param ReportResultInterface $result
     * @param OptimizationRuleInterface $optimizationRule
     * @return ReportResultInterface
     */
    private function addIdentifiersToTrainingData(ReportResultInterface $result, OptimizationRuleInterface $optimizationRule)
    {
        $identifierGenerators = $optimizationRule->getIdentifierFields();

        if (empty($identifierGenerators) || !is_array($identifierGenerators)) {
            return $result;
        }

        $collection = new Collection($result->getColumns(), $result->getRows(), $result->getTypes());

        $rows = $result->getRows();
        $newRows = new \SplDoublyLinkedList();

        foreach ($rows as $row) {
            foreach ($identifierGenerators as $identifierGenerator) {
                if (!array_key_exists($identifierGenerator, $row)) {
                    continue;
                }
                $row[OptimizationRuleInterface::IDENTIFIER_COLUMN] = $row[$identifierGenerator];
                $newRows->push($row);
            }

            unset($row);
        }

        unset($rows);
        $result->setRows($newRows);

        $columns = $collection->getColumns();
        $columns[OptimizationRuleInterface::IDENTIFIER_COLUMN] = OptimizationRuleInterface::IDENTIFIER_COLUMN;
        $result->setColumns($columns);

        $types = $collection->getTypes();
        $types[OptimizationRuleInterface::IDENTIFIER_COLUMN] = FieldType::DATE;
        $result->setTypes($types);

        $result->setTypes($collection->getTypes());

        return $result;
    }

    /**
     * @param $groupedRows
     * @param $row
     * @param $groupFields
     * @param array $numRowsOfEachGroup
     * @param $identifierFields
     * @return SplDoublyLinkedList
     */
    private function addRowToGroupedRows($groupedRows, $row, $groupFields, array &$numRowsOfEachGroup, $identifierFields)
    {
        /** @var SplDoublyLinkedList $groupedRows */
        if ($groupedRows->isEmpty()) {
            $groupedRows->push($row);

            $index = $groupedRows->count() - 1;
            $numRowsOfEachGroup[$index] = 1;

            return $groupedRows;
        }

        $mustGrouped = false;
        foreach ($groupedRows as $index => $groupedRow) {
            $mustGrouped = $this->isNeededGroup($row, $groupFields, $groupedRow);
            if ($mustGrouped) {
                $numRowsOfEachGroup[$index]++;
                $groupedRow = $this->group($row, $groupFields, $groupedRow, $identifierFields);
                $groupedRows->offsetSet($index, $groupedRow);
                break;
            }
        }

        if (!$mustGrouped) {
            $groupedRows->push($row);

            $index = $groupedRows->count() - 1;
            $numRowsOfEachGroup[$index] = 1;

        }

        return $groupedRows;
    }

    /**
     * @param $row
     * @param $groupFields
     * @param $groupedRow
     * @return bool
     */
    private function isNeededGroup($row, $groupFields, $groupedRow): bool
    {
        $mustGrouped = false;
        foreach ($groupFields as $field) {
            if ($row[$field] !== $groupedRow[$field]) {
                $mustGrouped = false;
                break;
            } else {
                $mustGrouped = true;
            }
        }
        return $mustGrouped;
    }

    /**
     * @param $row
     * @param $groupFields
     * @param $groupedRow
     * @param $identifierFields
     * @return mixed
     */
    private function group($row, $groupFields, $groupedRow, $identifierFields)
    {
        $identifierFields = is_array($identifierFields) ? $identifierFields : [$identifierFields];
        foreach ($groupedRow as $fieldName => $value) {
            if (in_array($fieldName, $groupFields) || in_array($fieldName, $identifierFields)) {
                $groupedRow[$fieldName] = $value;
            } else {
                if (is_numeric($groupedRow[$fieldName]) && is_numeric($row[$fieldName])) {
                    $groupedRow[$fieldName] += $row[$fieldName];
                } else {
                    $groupedRow[$fieldName] = $value;
                }
            }
        }
        return $groupedRow;
    }

    private function groupAndGenerateGlobalTrainingData(ReportResultInterface $reportResult, OptimizationRuleInterface $optimizationRule)
    {
        $segmentFields = $optimizationRule->getSegmentFields();

        if (empty($segmentFields)) {
            return $reportResult;
        }

        $allSubsetSegmentFields = $this->generateSubsetSegment($segmentFields);
        array_push($allSubsetSegmentFields, []); //important: Add empty subset segment for global training data for no segment

        $globalRows = new SplDoublyLinkedList();
        foreach ($allSubsetSegmentFields as $subsetSegmentField) {
            $globalRowsOfOneSubsetSegmentField = $this->groupAndGenerateGlobalTrainingDataForOneSegmentSubset($reportResult, $optimizationRule, $subsetSegmentField);
            foreach ($globalRowsOfOneSubsetSegmentField as $row) {
                $globalRows->push($row);
            }
        }

        $reportResult->setRows($globalRows);

        return $reportResult;
    }

    private function generateSubsetSegment($segmentFieldsValues, $minLength = 1)
    {
        $count = count($segmentFieldsValues);
        $members = pow(2, $count);
        $allSubsets = array();
        for ($i = 0; $i < $members; $i++) {
            $b = sprintf("%0" . $count . "b", $i);
            $oneSubset = array();
            for ($j = 0; $j < $count; $j++) {
                if ($b{$j} == '1') $oneSubset[] = $segmentFieldsValues[$j];
            }
            if (count($oneSubset) >= $minLength) {
                $allSubsets[] = $oneSubset;
            }
        }

        return $allSubsets;
    }

    private function groupAndGenerateGlobalTrainingDataForOneSegmentSubset(ReportResultInterface $reportResult, OptimizationRuleInterface $optimizationRule, array $subsetSegment)
    {
        $rows = $reportResult->getRows();
        $groupedRows = new SplDoublyLinkedList();
        $numRowsOfEachGroup = [];

        $segmentFields = $optimizationRule->getSegmentFields();
        $dateField = $optimizationRule->getDateField();

        // apply average Field from showInTotal Field in core_report_view table
        $averageFields = [];
        $reportView = $optimizationRule->getReportView();
        if ($reportView instanceof ReportViewInterface) {
            $showInTotals = $reportView->getShowInTotal();
            foreach ($showInTotals as &$showInTotal) {
                if (!is_array($showInTotal)) {
                    continue;
                }
                if (!array_key_exists('fields', $showInTotal) && !array_key_exists('type', $showInTotal)) {
                    continue;
                }
                if ($showInTotal['type'] == 'average') {
                    $averageFields = $showInTotal['fields'];
                    break;
                }
            }
        }

        $globalFields = array_diff($segmentFields, $subsetSegment);

        array_push($subsetSegment, $dateField, OptimizationRuleInterface::IDENTIFIER_COLUMN);
        $groupFields = array_unique($subsetSegment);

        foreach ($rows as $key => $row) {
            $groupedRows = $this->addRowToGroupedRows($groupedRows, $row, $groupFields, $numRowsOfEachGroup, $optimizationRule->getIdentifierFields());
        }

        //Calculate the average and set global for global fields.
        foreach ($groupedRows as $index => $groupedRow) {
            foreach ($groupedRow as $key => $value) {
                if (in_array($key, $averageFields)) {
                    $groupedRow[$key] = round($value / $numRowsOfEachGroup[$index], 12);
                }

                if (in_array($key, $globalFields)) {
                    $groupedRow[$key] = 'global';
                }
            }

            $groupedRows->offsetSet($index, $groupedRow);
        }

        return $groupedRows;
    }

    /**
     * @inheritdoc
     */
    public function getDataByIdentifiers(OptimizationRuleInterface $optimizationRule, $identifiers)
    {
        return $this->dataTrainingTableService->getDataByIdentifiers($optimizationRule, $identifiers);
    }

    /**
     * @param ReportResultInterface $reportResult
     * @param OptimizationRuleInterface $optimizationRule
     * @return ReportResultInterface
     */
    private function normalizeSegments(ReportResultInterface $reportResult, OptimizationRuleInterface $optimizationRule)
    {
        $segments = $optimizationRule->getSegmentFields();
        if (empty($segments) || empty($this->normalizers) || !is_array($this->normalizers)) {
            return $reportResult;
        }

        /// get data to test
        $example = array();
        $rows = $reportResult->getRows();
        $i = 0;
        foreach ($rows as $row) {
            $example[] = $row;
            $i++;
            if ($i >= NormalizerInterface::NUMBER_NORMALIZER_COUNT_SAMPLE_VALUE) break;
        }

        /// get normalizer for each segment
        $availableSegments = array();
        foreach ($this->normalizers as $key => $normalizer) {
            if (!$normalizer instanceof NormalizerInterface) {
                continue;
            }

            foreach ($segments as $segment) {
                if ($normalizer->isSupport($example, $segment)) {
                    $availableSegments[$segment][] = $normalizer;
                }
            }
        }

        if (empty($availableSegments)) {
            return $reportResult;
        }

        $rows->rewind();
        $newRows = new SplDoublyLinkedList();

        foreach ($rows as $row) {
            foreach ($availableSegments as $segment => $normalizers) {
                if (!array_key_exists($segment, $row)) {
                    continue;
                }

                foreach ($normalizers as $normalizer) {
                    if (!$normalizer instanceof NormalizerInterface) {
                        continue;
                    }

                    $row[$segment] = $normalizer->normalizeText($row[$segment]);
                }
            }

            $newRows->push($row);
            unset($row);
        }

        unset($rows);
        $reportResult->setRows($newRows);

        return $reportResult;
    }
}