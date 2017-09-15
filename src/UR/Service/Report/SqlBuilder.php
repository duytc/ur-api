<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PDO;
use UR\Behaviors\JoinConfigUtilTrait;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\DateRange;
use UR\Domain\DTO\Report\Filters\DateFilterInterface;
use UR\Domain\DTO\Report\Filters\FilterInterface;
use UR\Domain\DTO\Report\Filters\NumberFilter;
use UR\Domain\DTO\Report\Filters\NumberFilterInterface;
use UR\Domain\DTO\Report\Filters\TextFilter;
use UR\Domain\DTO\Report\Filters\TextFilterInterface;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\JoinBy\JoinFieldInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Domain\DTO\Report\Transforms\PostAggregationTransform;
use UR\Entity\Core\DataSet;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;
use UR\Service\PublicSimpleException;
use UR\Service\SqlUtilTrait;

class SqlBuilder implements SqlBuilderInterface
{
    use SqlUtilTrait;
    use JoinConfigUtilTrait;

    const STATEMENT_KEY = 'statement';
    const DATE_RANGE_KEY = 'dateRange';
    const SUB_QUERY = 'subQuery';
    const CONDITION_KEY = 'condition';

    const FIRST_ELEMENT = 0;
    const START_DATE_INDEX = 0;
    const END_DATE_INDEX = 1;
    const DATA_SET_TABLE_NAME_TEMPLATE = '__data_import_%d';

    const JOIN_CONFIG_JOIN_FIELDS = 'joinFields';
    const JOIN_CONFIG_OUTPUT_FIELD = 'outputField';
    const JOIN_CONFIG_VISIBLE = 'isVisible';
    const JOIN_CONFIG_MULTIPLE = 'isMultiple';
    const JOIN_CONFIG_FIELD = 'field';
    const JOIN_CONFIG_DATA_SET = 'dataSet';
    const JOIN_CONFIG_DATA_SETS = 'dataSets';

    const JOIN_PARAM_FROM_ALIAS = 'fromAlias';
    const JOIN_PARAM_TO_ALIAS = 'toAlias';
    const JOIN_PARAM_FROM_JOIN_FIELD = 'fromJoinField';
    const JOIN_PARAM_TO_JOIN_FIELD = 'toJoinField';
    const JOIN_PARAM_TO_TABLE_NAME = 'tableName';


    const AGGREGATE_METRICS_WHITE_LIST = '$$WHITE_LIST$$';
    const AGGREGATE_METRICS_SUM = '$$SUM$$';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * SqlBuilder constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->connection = $this->em->getConnection();
    }

    /**
     * @param ParamsInterface $params
     * @param null $overridingFilters
     * @return array
     * @throws PublicSimpleException
     */
    public function buildQueryForSingleDataSet(ParamsInterface $params, $overridingFilters = null)
    {
        $dataSet = $params->getDataSets()[0];
        $page = $params->getPage();
        $limit = $params->getLimit();
        $transforms = $params->getTransforms();
        $searches = $params->getSearches();
        $sortField = $params->getSortField();
        $sortDirection = $params->getOrderBy();

        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $filters = $dataSet->getFilters();

        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $tableColumns = array_keys($table->getColumns());
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
        $types = [];
        $realMetrics = $dataSetEntity->getMetrics();
        foreach ($realMetrics as $metric => $type) {
            $types[sprintf('%s_%d', $metric, $dataSet->getDataSetId())] = $type;
        }

        $realDimensions = $dataSetEntity->getDimensions();
        foreach ($realDimensions as $dimension => $type) {
            $types[sprintf('%s_%d', $dimension, $dataSet->getDataSetId())] = $type;
        }

        if ($searches === null) {
            $searches = [];
        }
        $searchFilters = $this->convertSearchToFilter($types, $searches);
        $filters = array_merge($filters, $searchFilters);

        /*
         * we get all fields from data set instead of selected fields in report view.
         * Notice: after that, we should filter all fields that is not yet selected.
         * This is important to allow use the none-selected fields in the transformers.
         * If not, the transformers have no value on none-selected fields, so that produce the null value
         */
        $fields = array_keys($dataSetEntity->getAllDimensionMetrics());
        // merge with dimensions, metrics of dataSetDTO because it contains hidden columns such as __date_month, __date_year, ...
        $hiddenFields = $this->getHiddenFieldsFromDataSetTable($table);
        $fields = array_merge($fields, $dimensions, $metrics, $hiddenFields);
        $fields = array_values(array_unique($fields));

        if (count($tableColumns) < 1) {
            throw new InvalidArgumentException(sprintf('The data set "%s" has no data', $dataSetEntity->getName()));
        }

        foreach ($fields as $index => $field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        if (empty($fields)) {
            throw new InvalidArgumentException(sprintf('The data set "%s" has no data', $dataSetEntity->getName()));
        }

        $this
            ->connection
            ->getWrappedConnection()
            ->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        $subQb = $this->connection->createQueryBuilder();

        $postAggregationFields = [];
        $timezone = 'UTC';
        $hasGroup = false;
        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $hasGroup = true;
                continue;
            }

            if ($transform instanceof PostAggregationTransform) {
                $postAggregationFields[] = $transform->getFieldName();
            }
        }

        $metricCalculations = $params->getMetricCalculations();
        if (!is_array($metricCalculations)) {
            $metricCalculations = [];
        }

        // Add SELECT clause
        foreach ($fields as $field) {
            $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
            $subQb->addSelect(sprintf('t.%s as %s', $this->connection->quoteIdentifier($field), $fieldWithId));
        }

        $newFields = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof AddCalculatedFieldTransform) {
                $subQb = $this->addCalculatedFieldTransformQuery($subQb, $transform, $newFields);
                continue;
            }

            if ($transform instanceof AddFieldTransform) {
                $subQb = $this->addNewFieldTransformQuery($subQb, $transform, $newFields);
                continue;
            }

            if ($transform instanceof ComparisonPercentTransform) {
                $subQb = $this->addComparisonPercentTransformQuery($subQb, $transform, $newFields);
                continue;
            }
        }

        $subQb->from($this->connection->quoteIdentifier($table->getName()), 't');

        // merge overriding filters
        if (is_array($overridingFilters) && count($overridingFilters) > 0) {
            /** @var FilterInterface $filter */
            foreach ($overridingFilters as $filter) {
                $filter->trimTrailingAlias($dataSet->getDataSetId());
            }

            $filters = array_merge($filters, $overridingFilters);
        }

        $dateRange = null;

        // Add WHERE clause
        if (empty($filters)) {
            $overwriteDateCondition = sprintf('%s IS NULL', $this->connection->quoteIdentifier(\UR\Model\Core\DataSetInterface::OVERWRITE_DATE));
            $subQb->where($overwriteDateCondition);
        } else {
            $buildResult = $this->buildFilters($filters);
            $conditions = $buildResult[self::CONDITION_KEY];
            $dateRange = $buildResult[self::DATE_RANGE_KEY];
            if (count($conditions) == 1) {
                $subQb->where($conditions[self::FIRST_ELEMENT]);
            } else {
                $subQb->where(implode(' AND ', $conditions));
            }
        }

        $qb = $this->connection->createQueryBuilder();
        foreach ($newFields as $newField) {
            if (in_array($newField, $metricCalculations) && $hasGroup) {
                $qb->addSelect(sprintf('SUM(%s) as %s', $this->connection->quoteIdentifier($newField), $this->connection->quoteIdentifier($newField)));
                continue;
            }

            $qb->addSelect(sprintf('%s as %s', $this->connection->quoteIdentifier($newField), $this->connection->quoteIdentifier($newField)));
        }

        foreach ($fields as $field) {
            $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
            if (in_array($fieldWithId, $metricCalculations) && $hasGroup) {
                $qb->addSelect(sprintf('SUM(%s) as %s', $this->connection->quoteIdentifier($fieldWithId), $this->connection->quoteIdentifier($fieldWithId)));
                continue;
            }

            if (array_key_exists($fieldWithId, $types) && $types[$fieldWithId] == FieldType::DATETIME && $hasGroup) {
                $qb->addSelect(sprintf("DATE(CONVERT_TZ(%s, 'UTC', '%s')) as %s", $this->connection->quoteIdentifier($fieldWithId), $timezone, $fieldWithId));
                continue;
            }

            $qb->addSelect(sprintf('%s', $this->connection->quoteIdentifier($fieldWithId)));
        }

        $qb = $this->addGroupByQuery($qb, $transforms, $types);
        $subQuery = $subQb->getSQL();
        $qb->from("($subQuery)", "sub1");

        $outerQb = $this->connection->createQueryBuilder();
        $outerQb = $this->addPostAggregationTransformQuery($outerQb, $transforms);

        // Add SELECT clause
        foreach ($fields as $field) {
            $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
            if (in_array($fieldWithId, $postAggregationFields)) {
                continue;
            }

            $outerQb->addSelect($this->connection->quoteIdentifier($fieldWithId));
        }

        foreach ($newFields as $newField) {
            $outerQb->addSelect($this->connection->quoteIdentifier($newField));
        }

        $subQuery = $qb->getSQL();
        $outerQb->from("($subQuery)", "sub2");
        $outerQb = $this->bindFilterParam($outerQb, $filters);
        $subQuery = clone $outerQb;
        $outerQb = $this->addLimitQuery($outerQb, $page, $limit);
        $outerQb = $this->addSortQuery($outerQb, $transforms, $sortField, $sortDirection);

        try {
            $stmt = $outerQb->execute();
        } catch (\Exception $e) {
            throw new PublicSimpleException('You have an error in your SQL syntax when run report. Please recheck report view');
        }

        return array(
            self::SUB_QUERY => $subQuery->getSQL(),
            self::STATEMENT_KEY => $stmt,
            self::DATE_RANGE_KEY => $dateRange
        );
    }

    /**
     * @param $subQuery
     * @param DataSetInterface $dataSet
     * @param array $transforms
     * @param array $searches
     * @param null $showInTotal
     * @param null $overridingFilters
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    public function buildGroupQueryForSingleDataSet($subQuery, DataSetInterface $dataSet, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null)
    {
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
        $types = [];
        $realMetrics = $dataSetEntity->getMetrics();
        foreach ($realMetrics as $metric => $type) {
            $types[sprintf('%s_%d', $metric, $dataSet->getDataSetId())] = $type;
        }

        $realDimensions = $dataSetEntity->getDimensions();
        foreach ($realDimensions as $dimension => $type) {
            $types[sprintf('%s_%d', $dimension, $dataSet->getDataSetId())] = $type;
        }

        $newFieldsTransform = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof NewFieldTransform) {
                $newFieldsTransform[] = $transform->getFieldName();
            }
        }

        $metrics = $showInTotal;
        if ($showInTotal === null) {
            $dataSetId = $dataSet->getDataSetId();
            $metrics = $dataSet->getMetrics();
            $dataSetRepository = $this->em->getRepository(DataSet::class);
            $dataSetObject = $dataSetRepository->find($dataSetId);
            if ($dataSetObject instanceof \UR\Model\Core\DataSetInterface) {
                $metrics = $dataSetObject->getMetrics();
                foreach ($metrics as $key => $type) {
                    if (in_array($type, [FieldType::NUMBER, FieldType::DECIMAL])) {
                        $metrics[sprintf('%s_%d', $key, $dataSetId)] = $type;
                    }

                    unset($metrics[$key]);
                }

                $metrics = array_keys($metrics);
            }
        }

        if (!is_array($metrics)) {
            $metrics = [];
        }

        $filters = $dataSet->getFilters();
        if (is_array($overridingFilters) && count($overridingFilters) > 0) {
            foreach ($overridingFilters as $filter) {
                $filter->trimTrailingAlias($dataSet->getDataSetId());
                $filters[] = $filter;
            }
        }

        if ($searches === null) {
            $searches = [];
        }
        $searchFilters = $this->convertSearchToFilter($types, $searches);
        $filters = array_merge($filters, $searchFilters);
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $tableColumns = array_keys($table->getColumns());

        foreach ($metrics as $key => $field) {
            if (in_array($field, $newFieldsTransform)) {
                continue;
            }

            $field = $this->removeIdSuffix($field);

            if (!in_array($field, $tableColumns)) {
                unset($metrics[$key]);
                continue;
            }

            $metrics[$key] = $field;
        }

        unset($field);
        $qb = $this->connection->createQueryBuilder();

        if (!empty($metrics)) {
            foreach ($metrics as $field) {
                if (in_array($field, $newFieldsTransform)) {
                    $qb->addSelect(sprintf('SUM(%s) as `%s`', $this->connection->quoteIdentifier($field), $field));
                    continue;
                }

                $qb->addSelect(sprintf('SUM(`%s_%d`) as %s_%d', $field, $dataSet->getDataSetId(), $field, $dataSet->getDataSetId()));
            }
        }

        $qb->addSelect('COUNT(*) as total');

        $qb->from("($subQuery)", "sub");
        $qb = $this->bindFilterParam($qb, $filters);

        return $qb->execute();
    }

    /**
     * @param ParamsInterface $params
     * @param null $overridingFilters
     * @return array
     * @throws PublicSimpleException
     */
    public function buildQuery(ParamsInterface $params, $overridingFilters = null)
    {
        $dataSets = $params->getDataSets();
        $joinConfig = $params->getJoinConfigs();
        $page = $params->getPage();
        $limit = $params->getLimit();
        $transforms = $params->getTransforms();
        $searches = $params->getSearches();
        $sortField = $params->getSortField();
        $sortDirection = $params->getOrderBy();

        if (empty($dataSets)) {
            throw new InvalidArgumentException('no dataSet');
        }

        if (count($dataSets) == 1) {
            $dataSet = $dataSets[self::FIRST_ELEMENT];
            if (!$dataSet instanceof DataSetInterface) {
                throw new RuntimeException('expect an DataSetInterface object');
            }

            return $this->buildQueryForSingleDataSet($params, $overridingFilters);
        }

        if (count($joinConfig) < 1) {
            throw new InvalidArgumentException('expect joined field is not empty array when multiple data sets is selected');
        }

        $this
            ->connection
            ->getWrappedConnection()
            ->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        $subQb = $this->connection->createQueryBuilder();
        $conditions = [];
        $dateRange = [];

        // add select clause
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $types = [];
        $dataSetIndexes = [];
        $hasGroup = false;
        $postAggregationFields = [];
        $timezone = 'UTC';
        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $hasGroup = true;
                $timezone = $transform->getTimezone();
                continue;
            }

            if (
                $transform instanceof PostAggregationTransform
            ) {
                $postAggregationFields[] = $transform->getFieldName();
            }
        }

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $dataSetIndexes[$dataSet->getDataSetId()] = $dataSetIndex;
            $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
            $metrics = $dataSetEntity->getMetrics();
            foreach ($metrics as $key => $field) {
                $metrics[sprintf('%s_%d', $key, $dataSet->getDataSetId())] = $field;
                unset($metrics[$key]);
            }

            $dimensions = $dataSetEntity->getDimensions();
            foreach ($dimensions as $key => $field) {
                $dimensions[sprintf('%s_%d', $key, $dataSet->getDataSetId())] = $field;
                unset($dimensions[$key]);
            }
            $types = array_merge($types, $metrics, $dimensions);
        }

        unset($metrics, $dimensions);

        if ($searches === null) {
            $searches = [];
        }

        // merge search filters to current filters
        $newSearchFilters = [];
        if (!empty($searches)) {
            $searchFilters = $this->convertSearchToFilter($types, $searches, $joinConfig);
            /** @var FilterInterface $searchFilter */
            foreach ($searchFilters as $searchFilter) {
                $field = $searchFilter->getFieldName();
                $idAndField = $this->getIdSuffixAndField($field);
                if ($idAndField) {
                    $newSearchFilters[$idAndField['id']][] = $searchFilter;
                }
            }
        }

        // merge overriding filters to current filters
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
                    $clonedFilter = $this->cloneFilter($filter);
                    $clonedFilter->setFieldName($idAndField['field']);
                    $newSearchFilters[$idAndField['id']][] = $clonedFilter;
                }
            }
        }

        // Add SELECT clause
        $selectedJoinFields = [];
        $allFilters = [];
        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $filters = $dataSet->getFilters();
            if (array_key_exists($dataSet->getDataSetId(), $newSearchFilters)) {
                $filters = array_merge($filters, $newSearchFilters[$dataSet->getDataSetId()]);
            }
            $allFilters = array_merge($allFilters, $filters);
            $subQb = $this->buildSelectQuery($params, $subQb, $dataSet, $dataSetIndex, $joinConfig, $selectedJoinFields, $hasGroup = false, [], $timezone);
            $buildResult = $this->buildFilters($filters, sprintf('t%d', $dataSetIndex), $dataSet->getDataSetId());
            $conditions = array_merge($conditions, $buildResult[self::CONDITION_KEY]);
            $dateRange = array_merge($dateRange, $buildResult[self::DATE_RANGE_KEY]);
        }

        $newFields = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof AddCalculatedFieldTransform) {
                $subQb = $this->addCalculatedFieldTransformQuery($subQb, $transform, $newFields, $dataSetIndexes);
                continue;
            }

            if ($transform instanceof AddFieldTransform) {
                $subQb = $this->addNewFieldTransformQuery($subQb, $transform, $newFields, $dataSetIndexes);
                continue;
            }

            if ($transform instanceof ComparisonPercentTransform) {
                $subQb = $this->addComparisonPercentTransformQuery($subQb, $transform, $newFields, $dataSetIndexes);
                continue;
            }
        }

        // add JOIN clause
        $dataSetIds = array_map(function (DataSetInterface $dataSet) {
            return $dataSet->getDataSetId();
        }, $dataSets);

        $subQuery = $this->buildJoinQueryForJoinConfig($subQb, $dataSetIds, $joinConfig);

        if (count($conditions) == 1) {
            $where = $conditions[self::FIRST_ELEMENT];
        } else {
            $where = implode(' AND ', $conditions);
        }

        $subQuery = sprintf('%s WHERE (%s)', $subQuery, $where);

        $qb = $this->connection->createQueryBuilder();
        $selectedJoinFields = [];
        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $qb = $this->buildSelectQuery($params, $qb, $dataSet, null, $joinConfig, $selectedJoinFields, $hasGroup = true, [], $timezone);
        }

        $metricCalculations = $params->getMetricCalculations();
        foreach ($newFields as $newField) {
            if (in_array($newField, $metricCalculations) && $hasGroup) {
                $qb->addSelect(sprintf('SUM(%s) as %s', $this->connection->quoteIdentifier($newField), $this->connection->quoteIdentifier($newField)));
                continue;
            }

            $qb->addSelect($this->connection->quoteIdentifier($newField));
        }

        $qb->from("($subQuery)", "sub1");
        $qb = $this->addGroupByQuery($qb, $transforms, $types);

        $outerQb = $this->connection->createQueryBuilder();
        $outerQb = $this->addPostAggregationTransformQuery($outerQb, $transforms);

        $selectedJoinFields = [];
        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $outerQb = $this->buildSelectQuery($params, $outerQb, $dataSet, null, $joinConfig, $selectedJoinFields, $hasGroup = false, $postAggregationFields, $timezone);
        }

        foreach ($newFields as $newField) {
            if (in_array($newField, $postAggregationFields)) {
                continue;
            }
            $outerQb->addSelect($this->connection->quoteIdentifier($newField));
        }

        $subQuery = $qb->getSQL();
        $outerQb->from("($subQuery)", "sub2");

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $filters = $dataSet->getFilters();
            if (array_key_exists($dataSet->getDataSetId(), $newSearchFilters)) {
                $filters = array_merge($filters, $newSearchFilters[$dataSet->getDataSetId()]);
            }

            $outerQb = $this->bindFilterParam($outerQb, $filters, $dataSet->getDataSetId());
        }

        $subQuery = clone $outerQb;
        $outerQb = $this->addLimitQuery($outerQb, $page, $limit);
        $outerQb = $this->addSortQuery($outerQb, $transforms, $sortField, $sortDirection);

        try {
            $stmt = $outerQb->execute();
        } catch (\Exception $e) {
            throw new PublicSimpleException('You have an error in your SQL syntax when run report. Please recheck report view');
        }

        return array(
            self::SUB_QUERY => $subQuery->getSQL(),
            self::STATEMENT_KEY => $stmt,
            self::DATE_RANGE_KEY => $dateRange
        );
    }

    /**
     * @param $subQuery
     * @param array $dataSets
     * @param array $joinConfig
     * @param array $transforms
     * @param array $searches
     * @param null $showInTotal
     * @param null $overridingFilters
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    public function buildGroupQuery($subQuery, array $dataSets, array $joinConfig, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null)
    {
        if (count($dataSets) == 1) {
            $dataSet = $dataSets[self::FIRST_ELEMENT];
            if (!$dataSet instanceof DataSetInterface) {
                throw new RuntimeException('expect an DataSetInterface object');
            }

            return $this->buildGroupQueryForSingleDataSet($subQuery, $dataSet, $transforms, $searches, $showInTotal, $overridingFilters);
        }

        if (count($joinConfig) < 1) {
            throw new InvalidArgumentException('expect joined field is not empty array when multiple data sets is selected');
        }

        $newFieldsTransform = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof NewFieldTransform) {
                $newFieldsTransform[] = $transform->getFieldName();
            }
        }

        $qb = $this->connection->createQueryBuilder();

        // add select clause
        $types = [];
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $dataSetIndexes = [];
        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $dataSetIndexes[$dataSet->getDataSetId()] = $dataSetIndex;
            $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
            $metrics = $dataSetEntity->getMetrics();
            foreach ($metrics as $key => $field) {
                $metrics[sprintf('%s_%d', $key, $dataSet->getDataSetId())] = $field;
                unset($metrics[$key]);
            }

            $dimensions = $dataSetEntity->getDimensions();
            foreach ($dimensions as $key => $field) {
                $dimensions[sprintf('%s_%d', $key, $dataSet->getDataSetId())] = $field;
                unset($dimensions[$key]);
            }
            $types = array_merge($types, $metrics, $dimensions);
            if ($showInTotal === null) {
                if ($dataSetEntity instanceof \UR\Model\Core\DataSetInterface) {
                    $metrics = $dataSetEntity->getMetrics();
                    foreach ($metrics as $key => $type) {
                        if (in_array($type, [FieldType::NUMBER, FieldType::DECIMAL])) {
                            $showInTotal[] = sprintf('%s_%d', $key, $dataSet->getDataSetId());
                        }
                    }
                }
            }
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
                    $clonedFilter = $this->cloneFilter($filter);
                    $clonedFilter->setFieldName($idAndField['field']);
                    $newSearchFilters[$idAndField['id']][] = $clonedFilter;
                }
            }
        }

        $metrics = [];
        foreach ($showInTotal as $field) {
            if (in_array($field, $newFieldsTransform)) {
                $metrics[] = $field;
                continue;
            }

            /** @var DataSetInterface $dataSet */
            foreach ($dataSets as $dataSetIndex => $dataSet) {
                $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
                $tableColumns = array_keys($table->getColumns());
                $fieldWithoutId = $this->removeIdSuffix($field);

                if (in_array($fieldWithoutId, $tableColumns)) {
                    $metrics[] = $field;
                    break;
                }
            }
        }

        if (!empty($metrics)) {
            foreach ($metrics as $field) {
                if (in_array($field, $newFieldsTransform)) {
                    $qb->addSelect(sprintf('SUM(%s) as `%s`', $this->connection->quoteIdentifier($field), $field));
                    continue;
                }

                $qb->addSelect(sprintf('SUM(`%s`) as %s', $field, $field));
            }
        }

        $qb->addSelect('COUNT(*) as total');
        $qb->from("($subQuery)", "sub");

        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $filters = $dataSet->getFilters();
            if (array_key_exists($dataSet->getDataSetId(), $newSearchFilters)) {
                $filters = array_merge($filters, $newSearchFilters[$dataSet->getDataSetId()]);
            }

            $qb = $this->bindFilterParam($qb, $filters, $dataSet->getDataSetId());
        }

        return $qb->execute();
    }


    /**
     * @param $filterFieldName
     * @return string
     */
    protected function getFieldNameInFilter($filterFieldName)
    {
        $underScoreCharacter = strpos($filterFieldName, '_');
        $fieldName = substr($filterFieldName, 0, $underScoreCharacter);

        return $fieldName;
    }

    /**
     * @param $filterFieldName
     * @return string
     */
    protected function getDataSetIdInFilter($filterFieldName)
    {
        $underScoreCharacter = strpos($filterFieldName, '_');
        $dataSetId = substr($filterFieldName, $underScoreCharacter + 1, strlen($filterFieldName));

        return $dataSetId;
    }


    /**
     * @param ParamsInterface $params
     * @param QueryBuilder $qb
     * @param DataSetInterface $dataSet
     * @param $dataSetIndex
     * @param array $joinConfig
     * @param array $selectedJoinFields
     * @param bool $hasGroup
     * @param array $postAggregationFields
     * @param string $timezone
     * @return QueryBuilder
     * @internal param $hasGroup
     */
    protected function buildSelectQuery(ParamsInterface $params, QueryBuilder $qb, DataSetInterface $dataSet, $dataSetIndex,
                array $joinConfig, array &$selectedJoinFields, $hasGroup = false, $postAggregationFields = [], $timezone = 'UTC')
    {
        $metrics = $dataSet->getMetrics();
        $dimensions = $dataSet->getDimensions();
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $tableColumns = array_keys($table->getColumns());
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        $dataSetEntity = $dataSetRepository->find($dataSet->getDataSetId());
        /*
         * TODO: this is duplicate code with buildQueryForSingleDataSet()
         * TODO: refactor to use a common function
         * we get all fields from data set instead of selected fields in report view.
         * Notice: after that, we should filter all fields that is not yet selected.
         * This is important to allow use the none-selected fields in the transformers.
         * If not, the transformers have no value on none-selected fields, so that produce the null value
         */
        $fields = array_keys($dataSetEntity->getAllDimensionMetrics());
        $types = array_merge($dataSetEntity->getMetrics(), $dataSetEntity->getDimensions());
        // merge with dimensions, metrics of dataSetDTO because it contains hidden columns such as __date_month, __date_year, ...
        $hiddenFields = $this->getHiddenFieldsFromDataSetTable($table);
        $fields = array_merge($fields, $dimensions, $metrics, $hiddenFields);
        $fields = array_values(array_unique($fields));
        // filter all fields that are not in table's columns
        foreach ($fields as $index => $field) {
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }
        // if no field is valid
        if (empty($fields)) {
            throw new InvalidArgumentException(sprintf('The data set "%s" has no data', $dataSetEntity->getName()));
        }
        $metricCalculation = $params->getMetricCalculations();
        // build select query for each data set
        foreach ($fields as $field) {
            $alias = $this->getAliasForField($dataSet->getDataSetId(), $field, $joinConfig);
            if ($alias === null) {
                continue;
            }
            $outputField = $this->checkFieldInJoinConfig($field, $dataSet->getDataSetId(), $joinConfig);
            if ($outputField) {
                if (in_array($outputField, $selectedJoinFields)) {
                    continue;
                }
                $selectedJoinFields[] = $outputField;
            }
            $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());

            if ($outputField) {
                $fieldWithId = $outputField;
            }

            if (in_array($fieldWithId, $postAggregationFields)) {
                continue;
            }

            $fieldName = $dataSetIndex !== null ? $this->connection->quoteIdentifier(sprintf('t%d.%s', $dataSetIndex, $field)) : $fieldWithId;
            if ($outputField && $dataSetIndex === null) {
                $fieldName = "`$outputField`";
            }
            if (in_array($fieldWithId, $metricCalculation) && $hasGroup) {
                $qb->addSelect(sprintf('SUM(%s) as `%s`', $fieldName, $alias));
                continue;
            }
            if (array_key_exists($field, $types) && $types[$field] == FieldType::DATETIME) {
                $qb->addSelect(sprintf("DATE(CONVERT_TZ(%s, 'UTC', '%s')) as `%s`", $fieldName, $timezone, $alias));
                continue;
            }
            $qb->addSelect(sprintf("%s as `%s`", $fieldName, $alias));
        }
        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @param DataSetInterface $dataSet
     * @param $dataSetIndex
     * @param array $joinConfig
     * @param array $showInTotal
     * @return QueryBuilder
     */
    protected function buildSelectGroupQuery(QueryBuilder $qb, DataSetInterface $dataSet, $dataSetIndex, array $joinConfig, array $showInTotal)
    {
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $tableColumns = array_keys($table->getColumns());
        $fields = $showInTotal;

        // filter all fields that are not in table's columns
        foreach ($fields as $index => &$field) {
            $field = $this->removeIdSuffix($field);
            if (!in_array($field, $tableColumns)) {
                unset($fields[$index]);
            }
        }

        unset($field);
        $fields = array_unique($fields);

        // if no field is valid
        if (empty($fields)) {
            return $qb;
        }

        // build select query for each data set
        foreach ($fields as $field) {
            $alias = $this->getAliasForField($dataSet->getDataSetId(), $field, $joinConfig);
            if ($alias === null) {
                continue;
            }
            $field = $this->connection->quoteIdentifier(sprintf('t%d.%s', $dataSetIndex, $field));
            $qb->addSelect(sprintf("SUM(%s) as '%s'", $field, $alias));
        }

        return $qb;
    }

    /**
     * build JOIN QUERY when there're 2 data set joining together by more than 2 fields
     *
     * @param $fromField
     * @param $field
     * @param $fromAlias
     * @param $alias
     * @return string
     */
    private function buildMultipleJoinCondition($fromField, $field, $fromAlias, $alias)
    {
        $fromFields = explode(',', $fromField);
        $fields = explode(',', $field);
        $conditions = [];

        foreach ($fromFields as $key => $value) {
            if ($value === null) {
                throw new InvalidArgumentException('Invalid join config');
            }

            $conditions[] = sprintf('%s.%s = %s.%s', $fromAlias, $value, $alias, $fields[$key]);
        }

        return implode(' AND ', $conditions);
    }

    /**
     * @param array $filters
     * @param null $tableAlias
     * @param null $dataSetId
     * @param $checkOverwriteDate
     * @return array
     */
    protected function buildFilters(array $filters, $tableAlias = null, $dataSetId = null, $checkOverwriteDate = true)
    {
        $filterKeys = [];
        $sqlConditions = [];
        $dateRanges = [];

        foreach ($filters as $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if ($dataSetId !== null) {
                $filter->trimTrailingAlias($dataSetId);
            }

            if (!array_key_exists($filter->getFieldName(), $filterKeys)) {
                $filterKeys[$filter->getFieldName()] = 1;
            } else {
                $filterKeys[$filter->getFieldName()]++;
            }

            if ($filter instanceof DateFilterInterface) {
                $dateRanges[] = new DateRange($filter->getStartDate(), $filter->getEndDate());
            }

            $sqlConditions[] = $this->buildSingleFilter($filter, $filterKeys, $tableAlias, $dataSetId);
        }

        $overrideDateField = $tableAlias !== null ? sprintf('%s.%s', $tableAlias, \UR\Model\Core\DataSetInterface::OVERWRITE_DATE) : \UR\Model\Core\DataSetInterface::OVERWRITE_DATE;
        if ($checkOverwriteDate) {
            $sqlConditions[] = sprintf('%s IS NULL', $overrideDateField);
        }

        return array(
            self::CONDITION_KEY => $sqlConditions,
            self::DATE_RANGE_KEY => $dateRanges
        );
    }

    /**
     * @param FilterInterface $filter
     * @param array $filterKeys
     * @param null $tableAlias
     * @param null $dataSetId
     * @return string
     */
    protected function buildSingleFilter(FilterInterface $filter, array $filterKeys, $tableAlias = null, $dataSetId = null)
    {
        $fieldName = $tableAlias !== null ? sprintf('%s.%s', $tableAlias, $filter->getFieldName()) : $filter->getFieldName();
        $fieldName = $this->connection->quoteIdentifier($fieldName);
        if ($filter instanceof DateFilterInterface) {
            if (!$filter->getStartDate() || !$filter->getEndDate()) {
                throw new InvalidArgumentException('invalid date range of filter');
            }

            return sprintf('(%s BETWEEN %s AND %s)', $fieldName, sprintf(':startDate%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0), sprintf(':endDate%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0));
        }

        $bindParamName = sprintf(':%s%d%d', $filter->getFieldName(), $filterKeys[$filter->getFieldName()], $dataSetId ?? 0);
        if ($filter instanceof NumberFilterInterface) {
            switch ($filter->getComparisonType()) {
                case NumberFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s <> %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_GREATER:
                    return sprintf('%s > %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_SMALLER:
                    return sprintf('%s < %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_SMALLER_OR_EQUAL:
                    return sprintf('%s <= %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_GREATER_OR_EQUAL:
                    return sprintf('%s >= %s', $fieldName, $bindParamName);

                case NumberFilter::COMPARISON_TYPE_IN:
                    return sprintf('%s IN (%s)', $fieldName, implode(',', $filter->getComparisonValue()));

                case NumberFilter::COMPARISON_TYPE_NOT_IN:
                    return sprintf('(%s IS NULL OR %s NOT IN (%s))', $fieldName, $fieldName, implode(',', $filter->getComparisonValue()));

                case TextFilter::COMPARISON_TYPE_NOT_NULL:
                    return sprintf('(%s IS NOT NULL)', $fieldName);

                case TextFilter::COMPARISON_TYPE_NULL:
                    return sprintf('(%s IS NULL)', $fieldName);

                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        if ($filter instanceof TextFilterInterface) {
            $textFilterComparisonValue = $filter->getComparisonValue();

            switch ($filter->getComparisonType()) {
                case TextFilter::COMPARISON_TYPE_EQUAL :
                    return sprintf('%s = %s', $fieldName, $bindParamName);

                case TextFilter::COMPARISON_TYPE_NOT_EQUAL:
                    return sprintf('%s <> %s', $fieldName, $textFilterComparisonValue);

                case TextFilter::COMPARISON_TYPE_CONTAINS :
                    $contains = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s LIKE \'%%%s%%\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s)", implode(' OR ', $contains)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_NOT_CONTAINS :
                    $notContains = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s NOT LIKE \'%%%s%%\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s IS NULL OR %s = '' OR %s)", $fieldName, $fieldName, implode(' AND ', $notContains)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_START_WITH:
                    $startWiths = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s LIKE \'%s%%\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s)", implode(' OR ', $startWiths)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_END_WITH:
                    $endWiths = array_map(function ($tcv) use ($fieldName) {
                        return sprintf('%s LIKE \'%%%s\'', $fieldName, $tcv);
                    }, $textFilterComparisonValue);

                    return sprintf("(%s)", implode(' OR ', $endWiths)); // cover conditions in "()" to keep inner AND/OR of conditions

                case TextFilter::COMPARISON_TYPE_IN:
                    $values = array_map(function ($value) {
                        return "'$value'";
                    }, $filter->getComparisonValue());
                    return sprintf('%s IN (%s)', $fieldName, implode(',', $values));

                case TextFilter::COMPARISON_TYPE_NOT_IN:
                    $values = array_map(function ($value) {
                        return "'$value'";
                    }, $filter->getComparisonValue());
                    return sprintf('(%s IS NULL OR %s = \'\' OR %s NOT IN (%s))', $fieldName, $fieldName, $fieldName, implode(',', $values));

                case TextFilter::COMPARISON_TYPE_NOT_NULL:
                    return sprintf('(%s IS NOT NULL)', $fieldName);

                case TextFilter::COMPARISON_TYPE_NULL:
                    return sprintf('(%s IS NULL)', $fieldName);

                default:
                    throw new InvalidArgumentException(sprintf('comparison type %d is not supported', $filter->getComparisonType()));
            }
        }

        throw new InvalidArgumentException(sprintf('filter is not supported'));
    }

    /**
     * @param $dataSetId
     * @return Table
     */
    protected function getDataSetTableSchema($dataSetId)
    {
        $sm = $this->connection->getSchemaManager();
        $tableName = sprintf(self::DATA_SET_TABLE_NAME_TEMPLATE, $dataSetId);

        return $sm->listTableDetails($tableName);
    }

    /**
     * @param QueryBuilder $qb
     * @param array $dataSetIds
     * @param array $joinConfig
     * @return string
     */
    protected function buildJoinQueryForJoinConfig(QueryBuilder $qb, array $dataSetIds, array $joinConfig)
    {
        $dataSetIndexes = array_flip($dataSetIds);

        // step1 : randomly select the first 2 data set
        $fromDataSet = current($dataSetIds);
        $table = $this->getDataSetTableSchema($fromDataSet);
        $qb->from($this->connection->quoteIdentifier($table->getName()), sprintf('t%s', $dataSetIndexes[$fromDataSet]));
        $selectQuery = $qb->getSQL();
        $onConditions = [];

        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            $onConditions[] = $this->buildJoinCondition($config, $dataSetIndexes);
        }

        $alias = $this->buildJoinAlias($dataSetIds, $fromDataSet, $dataSetIndexes);
        $joinQuery = sprintf('INNER JOIN (%s) ON (%s)',
            implode(',', $alias),
            implode(' AND ', $onConditions)
        );

        return sprintf('%s %s', $selectQuery, $joinQuery);
    }

    /**
     * @param $dataSetIds
     * @param $fromDataSet
     * @param $dataSetIndexes
     * @return array
     */
    protected function buildJoinAlias($dataSetIds, $fromDataSet, $dataSetIndexes)
    {
        $alias = [];

        foreach ($dataSetIds as $dataSetId) {
            if ($dataSetId == $fromDataSet) {
                continue;
            }

            $table = $this->getDataSetTableSchema($dataSetId);
            $alias[] = sprintf('%s as %s', $this->connection->quoteIdentifier($table->getName()), sprintf('t%s', $dataSetIndexes[$dataSetId]));
        }

        return $alias;
    }

    /**
     * @param JoinConfigInterface $joinConfig
     * @param array $dataSetIndexes
     * @return string
     */
    protected function buildJoinCondition(JoinConfigInterface $joinConfig, array $dataSetIndexes)
    {
        $fromAlias = '';
        $toAlias = '';
        $toJoinField = '';
        $fromJoinField = '';
        $joinFields = $joinConfig->getJoinFields();

        /** @var JoinFieldInterface $joinField */
        foreach ($joinFields as $index => $joinField) {
            $dataSetId = $joinField->getDataSet();
            if ($index == 0) {
                $fromAlias = sprintf('t%s', $dataSetIndexes[$dataSetId]);
                $fromJoinField = $joinField->getField();
                continue;
            }

            $toAlias = sprintf('t%s', $dataSetIndexes[$dataSetId]);
            $toJoinField = $joinField->getField();
        }

        if ($fromJoinField === null || $toJoinField === null) {
            throw new InvalidArgumentException('Invalid join config');
        }

        if (strpos($toJoinField, ',') !== false) {
            return $this->buildMultipleJoinCondition($fromJoinField, $toJoinField, $fromAlias, $toAlias);
        }

        $leftCondition = $this->connection->quoteIdentifier(sprintf('%s.%s', $fromAlias, $fromJoinField));
        $rightCondition = $this->connection->quoteIdentifier(sprintf('%s.%s', $toAlias, $toJoinField));
        return sprintf('%s = %s', $leftCondition, $rightCondition);
    }

    /**
     * @param Table $table
     * @return mixed
     */
    private function getHiddenFieldsFromDataSetTable($table)
    {
        $columns = $table->getColumns();
        $columns = array_filter($columns, function (Column $column) {
            return in_array($column->getType()->getName(), [Type::DATE, Type::DATETIME]);
        });
        $temporaryFields = [];
        /** @var Column $column */
        foreach ($columns as $column) {
            $temporaryFields[] = Synchronizer::getHiddenColumnDay($column->getName());
            $temporaryFields[] = Synchronizer::getHiddenColumnMonth($column->getName());
            $temporaryFields[] = Synchronizer::getHiddenColumnYear($column->getName());
        }

        return $temporaryFields;
    }
}