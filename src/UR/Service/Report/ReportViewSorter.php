<?php
namespace UR\Service\Report;

use UR\Service\DataSet\FieldType;

class ReportViewSorter implements ReportViewSorterInterface
{
    /**
     * @inheritdoc
     */
    public function sortReports($reportResult, $params)
    {
        $reports = array_values($reportResult->getReports());
        $types = $reportResult->getTypes();
        $sortField = $params->getSortField();
        $orderBy = $params->getOrderBy();

        if (count($reports) < 1) {
            return $reportResult;
        }

        if (!array_key_exists($sortField, $reports[0])) {
            return $reportResult;
        }

        if (!array_key_exists($sortField, $types)) {
            return $reportResult;
        }

        $type = $types[$sortField];

        usort($reports, function ($a, $b) use ($sortField, $orderBy, $type) {
            $firstValue = $a[$sortField];
            $secondValue = $b[$sortField];

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

        $reportResult->setReports($reports);

        return $reportResult;
    }
}