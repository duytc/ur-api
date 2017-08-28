<?php
namespace UR\Service\Report;

use SplDoublyLinkedList;
use UR\Service\DataSet\FieldType;

class ReportViewSorter implements ReportViewSorterInterface
{
    /**
     * @inheritdoc
     */
    public function sortReports($reportResult, $params)
    {
        $reports = iterator_to_array($reportResult->getRows());
        $types = $reportResult->getTypes();
        $sortField = $params->getSortField();
        $orderBy = $params->getOrderBy();

        if (count($reports) < 1) {
            return $reportResult;
        }

//        if (!array_key_exists($sortField, $reports[0])) {
//            return $reportResult;
//        }

        if (!array_key_exists($sortField, $types)) {
            $types[$sortField] = FieldType::NUMBER;
        }

        $type = $types[$sortField];

        usort($reports, function ($a, $b) use ($sortField, $orderBy, $type) {
            $firstValue = isset($a[$sortField]) ? $a[$sortField] : null;
            $secondValue = isset($b[$sortField]) ? $b[$sortField]: null;

            if ($firstValue == null && $secondValue == null) {
                return 0;
            }

            if ($firstValue == null) {
                return $orderBy == 'desc' ? 1 : -1;
            }

            if ($secondValue == null) {
                return $orderBy == 'desc' ? -1 : 1;
            }

            switch ($type) {
                case FieldType::NUMBER:
                    $firstValue = intval($firstValue);
                    $secondValue = intval($secondValue);
                    break;
                case FieldType::DECIMAL:
                    $firstValue = floatval($firstValue);
                    $secondValue = floatval($secondValue);
                    break;
                case FieldType::DATE:
                    $firstValue = \DateTime::createFromFormat("Y-m-d", $firstValue);
                    $secondValue = \DateTime::createFromFormat("Y-m-d", $secondValue);
                    break;
                case FieldType::DATETIME:
                    $firstValue = \DateTime::createFromFormat("Y-m-d H:i:s", $firstValue);
                    $secondValue = \DateTime::createFromFormat("Y-m-d H:i:s", $secondValue);
                    break;
            }

            return $orderBy == 'desc' ? ($firstValue < $secondValue) : ($firstValue > $secondValue);
        });

        $newRows = new SplDoublyLinkedList();
        foreach ($reports as $report) {
            $newRows->push($report);
        }

        unset($reports, $report);
        $reportResult->setRows($newRows);

        return $reportResult;
    }
}