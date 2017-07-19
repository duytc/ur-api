<?php


namespace UR\Service\Parser;

use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\Import\AutoImportData;

class DryRunReportFilter implements DryRunReportFilterInterface
{
    /**
     * @inheritdoc
     */
    public function filterReports(array $reportResult, DryRunParamsInterface $dryRunParams)
    {
        $reports = $reportResult[AutoImportData::DATA_REPORTS];
        $types = $reportResult[AutoImportData::DATA_TYPES];
        $searches = $dryRunParams->getSearches();

        foreach ($searches as $searchField => $searchContent) {
            if (!array_key_exists($searchField, $types)) {
                continue;
            }
            $type = $types[$searchField];

            foreach ($reports as $pos => &$report) {
                if (!array_key_exists($searchField, $report)) {
                    continue;
                }

                $value = $report[$searchField];

                //Filter number
                if ($type == FieldType::NUMBER || $type == FieldType::DECIMAL) {
                    $conditions = preg_split('/[\s]+/', $searchContent);
                    $value = $type == FieldType::NUMBER ? intval($value) : floatval($value);
                    foreach ($conditions as $condition) {
                        if (!$this->compareMathCondition($condition, $value)) {
                            unset($reports[$pos]);
                            continue;
                        }
                    }

                    continue;
                }

                //Filter text, date...
                if ($type == FieldType::TEXT || $type == FieldType::LARGE_TEXT || $type == FieldType::DATE || $type == FieldType::DATETIME) {
                    $words = explode(" ", $searchContent);
                    foreach ($words as $word) {
                        if (empty($word)) {
                            continue;
                        }
                        if (strpos(strtolower($value), strtolower($word)) === false) {
                            unset($reports[$pos]);
                            continue;
                        }
                    }

                    continue;
                }
            }
        }

        $reportResult[AutoImportData::DATA_REPORTS] = array_values($reports);

        return $reportResult;
    }

    /**
     * @param $condition
     * @param $value
     * @return bool
     */
    private function compareMathCondition($condition, $value)
    {
        if (preg_match('/([^\d]+)([0-9\.]+)/', $condition, $matches)) {
            $compareOperator = $matches[1];
            $compareValue = (float)$matches[2];

            switch ($compareOperator) {
                case '=':
                    return $value == $compareValue;
                case '==':
                    return $value == $compareValue;
                case '>':
                    return $value > $compareValue;
                case '>=':
                    return $value >= $compareValue;
                case '<':
                    return $value < $compareValue;
                case '<=':
                    return $value <= $compareValue;
                case '!':
                    return $value != $compareValue;
                case '!=':
                    return $value != $compareValue;
            }
        } else {
            if (empty($condition)) {
                return true;
            }

            return (float)$condition == $value;
        }

        return true;
    }
}