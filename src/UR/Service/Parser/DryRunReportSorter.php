<?php


namespace UR\Service\Parser;

use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\Import\AutoImportData;

class DryRunReportSorter implements DryRunReportSorterInterface
{
    /**
     * @inheritdoc
     */
    public function sortReports(array $reportResult, DryRunParamsInterface $dryRunParams)
    {
        $reports = array_values($reportResult[AutoImportData::DATA_REPORTS]);
        $types = $reportResult[AutoImportData::DATA_TYPES];
        $sortField = $dryRunParams->getSortField();
        $orderBy = $dryRunParams->getOrderBy();

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

            //$compare = $orderBy == 'desc' ? -1 : 1;
            return $orderBy == 'desc' ? ($firstValue < $secondValue) : ($firstValue > $secondValue);
        });

        $reportResult[AutoImportData::DATA_REPORTS] = $reports;

        return $reportResult;
    }
}