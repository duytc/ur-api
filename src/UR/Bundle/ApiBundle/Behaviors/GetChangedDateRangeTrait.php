<?php

namespace UR\Bundle\ApiBundle\Behaviors;


use DateTime;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;

trait GetChangedDateRangeTrait
{
    /**
     * get Date Range From Entries
     *
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param array $entryIds
     * @return array|\DateTime[] first for startDate, second for endDate
     */
    private function getDateRangeFromEntries(DataSourceEntryManagerInterface $dataSourceEntryManager, array $entryIds)
    {
        $dateRange = [];

        foreach ($entryIds as $entryId) {
            $dataSourceEntry = $dataSourceEntryManager->find($entryId);
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                continue;
            }

            if (!$dataSourceEntry->getDataSource()->isDateRangeDetectionEnabled()) {
                continue;
            }

            $startDate = $dataSourceEntry->getStartDate();
            $endDate = $dataSourceEntry->getEndDate();

            if (empty($dateRange)) {
                $dateRange = [
                    $dataSourceEntry->getStartDate(),
                    $dataSourceEntry->getEndDate()
                ];

                continue;
            }

            // override min startDate
            if ($startDate < $dateRange[0]) {
                $dateRange[0] = $startDate;
            }

            // override max endDate
            if ($endDate > $dateRange[1]) {
                $dateRange[1] = $endDate;
            }
        }

        return $dateRange;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return array
     */
    private function getChangedDateRangeForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource)
    {
        if (!$connectedDataSource->getDataSet()->isAutoReload()) {
            return [];
        }

        return [
            $connectedDataSource->getDataSource()->getDetectedStartDate(),
            $connectedDataSource->getDataSource()->getDetectedEndDate()
        ];
    }

    /**
     * @param DataSetInterface $dataSet
     * @return array
     */
    private function getDateRangeFromDataSet(DataSetInterface $dataSet)
    {
        $changedStartDate = null;
        $changedEndDate = null;
        $connectedDataSources = $dataSet->getConnectedDataSources();

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource->getDataSource()->isDateRangeDetectionEnabled()) {
                // if one data source is not enabled detect date range => known reload all
                return [
                    null,
                    null
                ];
            }

            if (is_null($changedStartDate) || is_null($changedEndDate)) {
                $changedStartDate = $connectedDataSource->getDataSource()->getDetectedStartDate();
                $changedEndDate = $connectedDataSource->getDataSource()->getDetectedEndDate();

                continue;
            }

            if ($changedStartDate > $connectedDataSource->getDataSource()->getDetectedStartDate()) {
                $changedStartDate = $connectedDataSource->getDataSource()->getDetectedStartDate();
            }

            if ($changedEndDate < $connectedDataSource->getDataSource()->getDetectedEndDate()) {
                $changedEndDate = $connectedDataSource->getDataSource()->getDetectedEndDate();
            }
        }

        return [$changedStartDate, $changedEndDate];
    }
}