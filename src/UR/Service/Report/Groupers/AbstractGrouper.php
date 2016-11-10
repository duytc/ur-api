<?php


namespace UR\Service\Report\Groupers;



abstract class AbstractGrouper implements GrouperInterface
{
    public function getGroupedReport($groupingField, array $reports, array $metrics)
    {
        $groupedReports = $this->generateGroupedArray($groupingField, $reports);

        foreach($groupedReports as $groupedReport) {
            foreach($groupedReport as $report) {

            }
        }
    }

    protected function generateGroupedArray($groupingField, array $reports)
    {
        $groupedArray = [];
        foreach($reports as $report) {
            if (!in_array($report[$groupingField], $groupedArray)) {
                $groupedArray[] = $report;
            }
        }

        return $groupedArray;
    }
}