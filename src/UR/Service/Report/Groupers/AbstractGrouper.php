<?php


namespace UR\Service\Report\Groupers;



use Doctrine\DBAL\Driver\Statement;

abstract class AbstractGrouper implements GrouperInterface
{
    public function getGroupedReport($groupingFields, Statement $statement, array $metrics, array $dimensions)
    {
        $groupedReports = $this->generateGroupedArray($groupingFields, $statement);

        $results = [];
        foreach($groupedReports as $groupedReport) {
            $result = current($groupedReport);

            // clear all metrics
            foreach($result as $key=>$value) {
                if (in_array($key, $metrics)) {
                    $result[$key] = 0;
                }
            }

            foreach($groupedReport as $report) {
                foreach ($report as $key=>$value) {
                    if (in_array($key, $metrics)) {
                        $result[$key] += $value;
                    }
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    protected function generateGroupedArray($groupingFields, Statement $statement)
    {
        $groupedArray = [];
        while ($report = $statement->fetch()) {
            if (!$report) {
                break;
            }

            $key = '';
            foreach ($groupingFields as $groupField) {
                if (array_key_exists($groupField, $report)) {
                    $key .= is_array($report[$groupField]) ? json_encode($report[$groupField], JSON_UNESCAPED_UNICODE) : $report[$groupField];
                }
            }
            $key = md5($key);
            $groupedArray[$key][] = $report;
        }
//        foreach($reports as $report) {
//            $key = '';
//            foreach ($groupingFields as $groupField) {
//                if (array_key_exists($groupField, $report)) {
//                    $key .= is_array($report[$groupField]) ? json_encode($report[$groupField], JSON_UNESCAPED_UNICODE) : $report[$groupField];
//                }
//            }
//            $key = md5($key);
//            $groupedArray[$key][] = $report;
//        }

        return $groupedArray;
    }
}