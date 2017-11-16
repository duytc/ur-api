<?php

namespace UR\Behaviors;

use Doctrine\DBAL\Query\QueryBuilder;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\Filters\AbstractFilter;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\ParamsInterface;

trait ReportViewFilterUtilTrait
{
    /**
     * @param QueryBuilder $qb
     * @param array $dataSets
     * @param array $searches
     * @param null $overridingFilters
     * @param array $types
     * @param array $joinConfig
     * @param bool $forShowTotal
     * @return QueryBuilder
     */
    private function applyFiltersForMultiDataSets(QueryBuilder $qb, array $dataSets, $searches = [], $overridingFilters = null, array $types, array $joinConfig, $forShowTotal = false)
    {
        $allFilters = $this->getFiltersForMultiDataSets($dataSets, $searches, $overridingFilters, $types, $joinConfig, $forShowTotal);

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
     * @param QueryBuilder $finalQb
     * @param DataSetInterface $dataSet
     * @param ParamsInterface $params
     * @param array $searches
     * @param $overridingFilters
     * @param array $types
     * @return mixed
     */
    private function applyFiltersForSingleDataSetForTemporaryTables(QueryBuilder $finalQb, DataSetInterface $dataSet, ParamsInterface $params, array $searches, $overridingFilters, array $types)
    {
        $filters = $this->getFiltersForSingleDataSet($searches, $overridingFilters, $types, $dataSet);

        $allDimensionMetrics = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());

        $filters = array_filter($filters, function ($filter) use ($allDimensionMetrics) {
              return $filter instanceof FilterInterface && in_array($filter->getFieldName(), $allDimensionMetrics);
        });

        if (empty($filters)) {
            $finalQb->where(sprintf('%s IS NULL', \UR\Model\Core\DataSetInterface::OVERWRITE_DATE));
            return $finalQb;
        }

        $maps = $params->getMagicMaps();

        $buildResult = $this->buildFilters($filters, null, $dataSet->getDataSetId());
        $conditions = $buildResult[self::CONDITION_KEY];
        foreach ($conditions as &$condition) {
            foreach ($maps as $key => $fieldSQL) {
                $condition = str_replace(sprintf("`%s`", $key), $fieldSQL, $condition);
            }
        }

        $conditions[] = sprintf('%s IS NULL', \UR\Model\Core\DataSetInterface::OVERWRITE_DATE);

        if (count($conditions) == 1) {
            $finalQb->where($conditions[self::FIRST_ELEMENT]);
        } else {
            $finalQb->where(implode(' AND ', $conditions));
        }


        return $finalQb;
    }

    /**
     * @param string $subQuery
     * @param DataSetInterface $dataSet
     * @param ParamsInterface $params
     * @param $filters
     * @return mixed
     */
    private function applyFiltersForMultiDataSetsForTemporaryTables(string  $subQuery, DataSetInterface $dataSet, ParamsInterface $params, $filters)
    {
        $allDimensionMetrics = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        $allDimensionMetrics = array_map(function ($field) use ($dataSet) {
            return sprintf("%s_%s", $field, $dataSet->getDataSetId());
        }, $allDimensionMetrics);

        $filters = array_filter($filters, function($filter) use ($allDimensionMetrics){
            return $filter instanceof AbstractFilter && in_array($filter->getFieldName(), $allDimensionMetrics);
        });

        if (empty($filters)) {
            return $subQuery;
        }

        $buildResult = $this->buildFilters($filters);
        $conditions = $buildResult[self::CONDITION_KEY];
        foreach ($conditions as &$condition) {
            $maps = $params->getMagicMaps();
            foreach ($maps as $key => $field) {
                $fieldReplace = "`" . str_replace(".", "`.", $field);
                $condition = str_replace(sprintf("`%s`", $key), $fieldReplace, $condition);
            }
        }

        $needWhere = strpos($subQuery, "WHERE") != false ? " AND " : " WHERE ";

        if (count($conditions) == 1) {
            $subQuery = $subQuery . $needWhere . ($conditions[self::FIRST_ELEMENT]);
        } else {
            $subQuery = $subQuery . $needWhere . (implode(' AND ', $conditions));
        }

        return $subQuery;
    }

    /**
     * @param array $dataSets
     * @param array $searches
     * @param $overridingFilters
     * @param array $types
     * @param array $joinConfig
     * @param bool $forShowTotal
     * @return array
     */
    private function getFiltersForMultiDataSets(array $dataSets, array $searches, $overridingFilters, array $types, array $joinConfig, $forShowTotal = false)
    {
        $allFilters = [];
        $newSearchFilters = [];

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $filters = $dataSet->getFilters();
            if (!is_array($filters)) {
                $filters = [];
            }

            foreach ($filters as &$filter) {
                if (!$filter instanceof FilterInterface) {
                    continue;
                }

                $fieldName = $filter->getFieldName();
                if (strpos($fieldName, sprintf("_". $dataSet->getDataSetId())) == false) {
                    $filter->setFieldName(sprintf("%s_%s", $fieldName, $dataSet->getDataSetId()));
                }
            }

            $allFilters = array_merge($allFilters, $filters);
        }

        foreach ($allFilters as $filter) {
            $field = $filter->getFieldName();
            $idAndField = $this->getIdSuffixAndField($field);
            if ($idAndField) {
                $newSearchFilters[$idAndField['id']][] = $filter;
            } else {
                $newSearchFilters[0][] = $filter;
            }
        }

        if (!empty($searches)) {
            $searchFilters = $this->convertSearchToFilter($types, $searches, $joinConfig);
            /** @var FilterInterface $searchFilter */
            foreach ($searchFilters as $searchFilter) {
                $field = $searchFilter->getFieldName();
                $idAndField = $this->getIdSuffixAndField($field);
                if ($idAndField) {
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
                    $newSearchFilters[$idAndField['id']][] = $clonedFilter;
                }
            }
        }

        $allFilters = [];
        foreach ($newSearchFilters as $field => $fieldFilters) {
            if (!is_array($fieldFilters)) {
                continue;
            }
            $allFilters = array_merge($allFilters, $fieldFilters);
        }

        $allFilters = $this->normalizeFiltersWithDataSets($allFilters, $dataSets);

        if ($forShowTotal) {
            $allFilters = $this->normalizeFiltersWithJoinConfig($allFilters, $joinConfig);
        }

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