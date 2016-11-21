<?php
namespace UR\Service\Report\Groupers;

use UR\Service\DTO\Collection;

abstract class AbstractGrouper implements GrouperInterface
{
    public function getGroupedReport($groupingFields, Collection $collection, array $metrics, array $dimensions)
    {
        $groupedReports = $this->generateGroupedArray($groupingFields, $collection, $dimensions);

        $results = [];
        foreach ($groupedReports as $groupedReport) {
            $result = current($groupedReport);

            // clear all metrics
            foreach ($result as $key => $value) {
                if (in_array($key, $metrics)) {
                    $result[$key] = 0;
                }
            }

            foreach ($groupedReport as $report) {
                foreach ($report as $key => $value) {
                    if (in_array($key, $metrics)) {
                        $result[$key] += $value;
                    }
                }
            }

            $results[] = $result;
        }

        return $results;
    }

    protected function generateGroupedArray($groupingFields, Collection $collection, $dimensions)
    {
        $groupedArray = [];
        $rows = $collection->getRows();
        foreach ($rows as $report) {
            $key = '';
            foreach ($groupingFields as $groupField) {
                if (array_key_exists($groupField, $report)) {
                    $key .= is_array($report[$groupField]) ? json_encode($report[$groupField], JSON_UNESCAPED_UNICODE) : $report[$groupField];
                }
            }

            //Note: Remove all dimensions that do not group
            foreach ($dimensions as $dimension) {
                if (in_array($dimension, $groupingFields)) {
                    continue;
                }
                unset($report[$dimension]);
            }

            $key = md5($key);
            $groupedArray[$key][] = $report;

        }

        return $groupedArray;
    }
}