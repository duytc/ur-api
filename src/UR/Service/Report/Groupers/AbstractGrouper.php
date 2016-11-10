<?php


namespace UR\Service\Report\Groupers;



abstract class AbstractGrouper implements GrouperInterface
{
    public function getGroupedReport($groupingField, array $reports, array $metrics, array $dimensions)
    {
        $groupedReports = $this->generateGroupedArray($groupingField, $reports);

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

    protected function generateGroupedArray($groupingField, array $reports)
    {
        $groupedArray = [];
        foreach($reports as $report) {
            $groupedArray[$report[$groupingField]][] = $report;
        }

        return $groupedArray;
    }
}