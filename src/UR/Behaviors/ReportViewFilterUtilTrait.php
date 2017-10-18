<?php

namespace UR\Behaviors;

use Doctrine\DBAL\Query\QueryBuilder;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Filters\FilterInterface;

trait ReportViewFilterUtilTrait
{
    /**
     * @param QueryBuilder $qb
     * @param array $types
     * @param array $dataSets
     * @param array $joinConfig
     * @param array $searches
     * @param null $overridingFilters
     * @return QueryBuilder
     */
    private function applyFiltersForMultiDataSets(QueryBuilder $qb, array $dataSets, $searches = [], $overridingFilters = null, array $types, array $joinConfig)
    {
        $allFilters = $this->getFiltersForMultiDataSets($dataSets, $searches, $overridingFilters, $types, $joinConfig);

        /**
         * Apply filters = filters from report view + filters from data sets + search filters
         */
        if (!empty($allFilters)) {
            $buildResult = $this->buildFilters($allFilters);
            $conditions = $buildResult[self::CONDITION_KEY];
            if (count($conditions) == 1) {
                $qb->where($conditions[self::FIRST_ELEMENT]);
            } else {
                $qb->where(implode(' AND ', $conditions));
            }
            $qb = $this->bindFilterParam($qb, $allFilters);
        }

        return $qb;
    }

    /**
     * @param QueryBuilder $finalQb
     * @param DataSetInterface $dataSet
     * @param array $searches
     * @param $overridingFilters
     * @param array $types
     * @return QueryBuilder
     */
    private function applyFiltersForSingleDataSet(QueryBuilder $finalQb, DataSetInterface $dataSet, array $searches, $overridingFilters, array $types)
    {
        $filters = $this->getFiltersForSingleDataSet($searches, $overridingFilters, $types, $dataSet);

        // Add WHERE clause
        if (!empty($filters)) {
            $buildResult = $this->buildFilters($filters, null, $dataSet->getDataSetId());
            $conditions = $buildResult[self::CONDITION_KEY];
            if (count($conditions) == 1) {
                $finalQb->where($conditions[self::FIRST_ELEMENT]);
            } else {
                $finalQb->where(implode(' AND ', $conditions));
            }
        }

        $finalQb = $this->bindFilterParam($finalQb, $filters, $dataSet->getDataSetId());

        return $finalQb;
    }

    /**
     * @param array $dataSets
     * @param array $searches
     * @param $overridingFilters
     * @param array $types
     * @param array $joinConfig
     * @return array
     */
    private function getFiltersForMultiDataSets(array $dataSets, array $searches, $overridingFilters, array $types, array $joinConfig)
    {
        $allFilters = [];

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $filters = $dataSet->getFilters();
            if (!is_array($filters)) {
                $filters = [];
            }

            $allFilters = array_merge($allFilters, $filters);
        }

        $newSearchFilters = [];
        if (!empty($searches)) {
            $searchFilters = $this->convertSearchToFilter($types, $searches, $joinConfig);
            /** @var FilterInterface $searchFilter */
            foreach ($searchFilters as $searchFilter) {
                $field = $searchFilter->getFieldName();
                $idAndField = $this->getIdSuffixAndField($field);
                if ($idAndField) {
                    $searchFilter->setFieldName($idAndField['field']);
                    $newSearchFilters[$idAndField['id']][] = $searchFilter;
                } else {
                    $newSearchFilters[0][] = $searchFilter;
                }
            }
        }

        if (is_array($overridingFilters) && count($overridingFilters) > 0) {
            /** @var FilterInterface $filter */
            foreach ($overridingFilters as $filter) {
                $field = $filter->getFieldName();
                $alias = $this->convertOutputJoinField($field, $joinConfig);
                if ($alias) {
                    $field = $alias;
                }
                $idAndField = $this->getIdSuffixAndField($field);
                if ($idAndField) {
                    /** @var FilterInterface $clonedFilter */
                    $clonedFilter = $this->cloneFilter($filter);
                    $clonedFilter->setFieldName($idAndField['field']);
                    $newSearchFilters[$idAndField['id']][] = $clonedFilter;
                }
            }
        }

        foreach ($newSearchFilters as $field => $fieldFilters) {
            if (!is_array($fieldFilters)) {
                continue;
            }
            $allFilters = array_merge($allFilters, $fieldFilters);
        }

        $allFilters = $this->normalizeFiltersWithDataSets($allFilters, $dataSets);
        $allFilters = $this->normalizeFiltersWithJoinConfig($allFilters, $joinConfig);

        return $allFilters;
    }

    /**
     * @param DataSetInterface $dataSet
     * @param array $searches
     * @param $overridingFilters
     * @param array $types
     * @return array|mixed
     */
    private function getFiltersForSingleDataSet(array $searches, $overridingFilters, array $types, DataSetInterface $dataSet)
    {
        $filters = $dataSet->getFilters();

        if ($searches === null) {
            $searches = [];
        }

        $searchFilters = $this->convertSearchToFilter($types, $searches);
        $filters = array_merge($filters, $searchFilters);

        // merge overriding filters
        if (is_array($overridingFilters) && count($overridingFilters) > 0) {
            /** @var FilterInterface $filter */
            foreach ($overridingFilters as $filter) {
                $filter->trimTrailingAlias($dataSet->getDataSetId());
            }

            $filters = array_merge($filters, $overridingFilters);
        }

        return $filters;
    }
}