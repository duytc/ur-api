<?php
namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Behaviors\JoinConfigUtilTrait;
use UR\Behaviors\ReformatDataTrait;
use UR\Behaviors\ReportViewFilterUtilTrait;
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
use UR\Domain\DTO\Report\Transforms\AddConditionValueTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\NewFieldTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\ReportViewAddConditionalTransformValue;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Repository\Core\ReportViewAddConditionalTransformValueRepositoryInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;
use UR\Service\PublicSimpleException;
use UR\Service\SqlUtilTrait;

class SqlBuilder implements SqlBuilderInterface
{
    use SqlUtilTrait;
    use JoinConfigUtilTrait;
    use ReportViewFilterUtilTrait;
    use ReformatDataTrait;

    const STATEMENT_KEY = 'statement';
    const DATE_RANGE_KEY = 'dateRange';
    const SUB_QUERY = 'subQuery';
    const CONDITION_KEY = 'condition';
    const ROWS = 'rows';
    const TOTAl_ROWS = 'total_rows';

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

    /** @var  Synchronizer */
    protected $sync;

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
     * @return Synchronizer
     */
    public function getSync()
    {
        if (!$this->sync instanceof Synchronizer) {
            $this->sync = new Synchronizer($this->connection, new Comparator());
        }

        return $this->sync;
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

        $types = array_merge($types, $params->getFieldTypes());

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
        $aggregationFields = [];
        $isAggregateAll = false;

        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $hasGroup = true;
                $timezone = $transform->getTimezone();

                $isAggregateAll = $transform->isAggregateAll();
                if ($isAggregateAll) {
                    $aggregationFields = [];
                } else {
                    $aggregationFields = $transform->getAggregateFields();
                }
                continue;
            }
        }

        // Add SELECT clause
        foreach ($fields as $field) {
            $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
            $subQb->addSelect(sprintf('t.%s as %s', $this->connection->quoteIdentifier($field), $fieldWithId));
        }

        /* do pre transform */
        $newFields = [];
        foreach ($transforms as $transform) {
            if (!$transform instanceof TransformInterface) {
                continue;
            }

            if ($transform->getIsPostGroup()) {
                continue;
            }

            if ($transform instanceof AddCalculatedFieldTransform) {
                $subQb = $this->addCalculatedFieldTransformQuery($subQb, $transform, $newFields, [], [] , $removeSuffix = true);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }

            if ($transform instanceof AddFieldTransform) {
                $subQb = $this->addNewFieldTransformQuery($subQb, $transform, $newFields, [], []);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }

            if ($transform instanceof AddConditionValueTransform) {
                // patch values for AddConditionValueTransform
                $transform = $this->patchAddConditionValueTransform($transform);

                $subQb = $this->addConditionValueTransformQuery($subQb, $transform, $newFields, [], [], $removeSuffix = true);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }

            if ($transform instanceof ComparisonPercentTransform) {
                $subQb = $this->addComparisonPercentTransformQuery($subQb, $transform, $newFields, [], $removeSuffix = true);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }
        }

        $subQb->from($this->connection->quoteIdentifier($table->getName()), 't');

        $sqlConditions = sprintf('%s IS NULL', \UR\Model\Core\DataSetInterface::OVERWRITE_DATE);
        $subQb->where($sqlConditions);

        $fromQuery = $subQb->getSQL();

        /* do group */
        if ($hasGroup) {
            $qb = $this->connection->createQueryBuilder();
            foreach ($newFields as $newField) {
                if (($isAggregateAll || in_array($newField, $aggregationFields)) && $hasGroup && in_array($types[$newField], [FieldType::NUMBER, FieldType::DECIMAL])) {
                    $qb->addSelect(sprintf('SUM(%s) as %s', $this->connection->quoteIdentifier($newField), $this->connection->quoteIdentifier($newField)));
                    continue;
                }

                $qb->addSelect(sprintf('%s as %s', $this->connection->quoteIdentifier($newField), $this->connection->quoteIdentifier($newField)));
            }

            foreach ($fields as $field) {
                $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
                if ($isAggregateAll && $hasGroup && array_key_exists($fieldWithId, $types) && in_array($types[$fieldWithId], [FieldType::NUMBER, FieldType::DECIMAL])){
                    $qb->addSelect(sprintf('SUM(%s) as %s', $this->connection->quoteIdentifier($fieldWithId), $this->connection->quoteIdentifier($fieldWithId)));
                    continue;
                }

                if (in_array($fieldWithId, $aggregationFields) && $hasGroup) {
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
            $qb->from("($fromQuery)", "sub1");
            $fromQuery = $qb->getSQL();
        }

        $outerQb = $this->connection->createQueryBuilder();

        /* do post transform */
        $newFieldsOnPost = [];
        foreach ($transforms as $transform) {
            if (!$transform instanceof TransformInterface) {
                continue;
            }

            if (!$transform->getIsPostGroup()) {
                continue;
            }

            if ($transform instanceof AddCalculatedFieldTransform) {
                $outerQb = $this->addCalculatedFieldTransformQuery($outerQb, $transform, $newFieldsOnPost, [], [], $removeSuffix = false);
                continue;
            }

            if ($transform instanceof AddFieldTransform) {
                $outerQb = $this->addNewFieldTransformQuery($outerQb, $transform, $newFieldsOnPost, [], []);
                continue;
            }

            if ($transform instanceof AddConditionValueTransform) {
                // patch values for AddConditionValueTransform
                $transform = $this->patchAddConditionValueTransform($transform);

                $outerQb = $this->addConditionValueTransformQuery($outerQb, $transform, $newFieldsOnPost, [], [], $removeSuffix = false);
                continue;
            }

            if ($transform instanceof ComparisonPercentTransform) {
                $outerQb = $this->addComparisonPercentTransformQuery($outerQb, $transform, $newFieldsOnPost, [], false);
                continue;
            }
        }

        // Add SELECT clause
        foreach ($fields as $field) {
            $fieldWithId = sprintf('%s_%d', $field, $dataSet->getDataSetId());
            if (in_array($fieldWithId, $postAggregationFields)) {
                continue;
            }

            $outerQb->addSelect($this->connection->quoteIdentifier($fieldWithId));
        }

        foreach ($newFields as $newField) {
            if (in_array($newField, $postAggregationFields)) {
                continue;
            }

            $outerQb->addSelect($this->connection->quoteIdentifier($newField));
        }

        $outerQb->from("($fromQuery)", "sub2");

        $finalQb = $this->connection->createQueryBuilder();
        $finalQb->select("*");
        $fromQuery = $outerQb->getSQL();
        $finalQb->from("($fromQuery)", "sub2");

        $grouperQuery = $finalQb->getSQL();

        $dateRange = null;

        $finalQb = $this->applyFiltersForSingleDataSet($finalQb, $dataSet, $searches, $overridingFilters, $types);

        $totalRowsQuery = $finalQb->getSQL();
        $totalRowsQb = $this->connection->createQueryBuilder();
        $totalRowsQb->select("count(*)");
        $totalRowsQb->from("($totalRowsQuery)", "sub3");
        $totalRowsQb->addOrderBy('NULL');

        /** Not call build filters because we don't have any column because of count(*) */
        $filters = $this->getFiltersForSingleDataSet($searches, $overridingFilters, $types, $dataSet);
        $totalRowsQb = $this->bindFilterParam($totalRowsQb, $filters, $dataSet->getDataSetId());

        $finalQb = $this->addSortQuery($finalQb, $transforms, $sortField, $sortDirection);
        if (empty($sortField)) {
            // add ORDER BY NULL for performance
            $finalQb->addOrderBy('NULL');
        }
        $finalQb = $this->addLimitQuery($finalQb, $page, $limit);
        $rows = new SplDoublyLinkedList();
        $total = 0;
        try {
            $stmt = $finalQb->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $rows->push($row);
            }
            $stmtAllRows = $totalRowsQb->execute();
            while ($row = $stmtAllRows->fetch(\PDO::FETCH_ASSOC)) {
                $total = reset($row);
                break;
            }
        } catch (\Exception $e) {
            throw new PublicSimpleException('You have an error in your SQL syntax when run report. Please recheck report view');
        }

        return array(
            self::SUB_QUERY => $grouperQuery,
            self::STATEMENT_KEY => $stmt,
            self::DATE_RANGE_KEY => $dateRange,
            self::ROWS => $rows,
            self::TOTAl_ROWS => $total
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
                $types[$transform->getFieldName()] = $transform->getType();
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

        $qb = $this->applyFiltersForSingleDataSet($qb, $dataSet, $searches, $overridingFilters, $types);

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
        $hasSort = false;
        $postAggregationFields = [];
        $timezone = 'UTC';
        $aggregationFields = [];
        $isAggregateAll = false;

        foreach ($transforms as $transform) {
            if ($transform instanceof GroupByTransform) {
                $hasGroup = true;
                $timezone = $transform->getTimezone();

                $isAggregateAll = $transform->isAggregateAll();
                if ($isAggregateAll) {
                    $aggregationFields = [];
                } else {
                    $aggregationFields = $transform->getAggregateFields();
                }

                continue;
            }

            if ($transform instanceof SortByTransform) {
                $hasSort = true;
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

        $types = array_merge($types, $params->getFieldTypes());

        unset($metrics, $dimensions);

        // Add SELECT clause
        $selectedJoinFields = [];
        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $subQb = $this->buildSelectQuery($isAggregateAll, $aggregationFields, $subQb, $dataSet, $dataSetIndex, $joinConfig, $selectedJoinFields, false, [], $timezone);
        }

        $newFields = [];
        foreach ($transforms as $transform) {
            if (!$transform instanceof TransformInterface) {
                continue;
            }

            if ($transform->getIsPostGroup()) {
                continue;
            }

            if ($transform instanceof AddCalculatedFieldTransform) {
                $subQb = $this->addCalculatedFieldTransformQuery($subQb, $transform, $newFields, $dataSetIndexes, $joinConfig, true);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }

            if ($transform instanceof AddFieldTransform) {
                $subQb = $this->addNewFieldTransformQuery($subQb, $transform, $newFields, $dataSetIndexes, $joinConfig);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }

            if ($transform instanceof AddConditionValueTransform) {
                // patch values for AddConditionValueTransform
                $transform = $this->patchAddConditionValueTransform($transform);

                $subQb = $this->addConditionValueTransformQuery($subQb, $transform, $newFields, $dataSetIndexes, $joinConfig, $removeSuffix = true);
                $types[$transform->getFieldName()] = $transform->getType();
                continue;
            }

            if ($transform instanceof ComparisonPercentTransform) {
                $subQb = $this->addComparisonPercentTransformQuery($subQb, $transform, $newFields, $dataSetIndexes, true);
                $types[$transform->getFieldName()] = $transform->getType();
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

        if (!empty($where)) {
            $subQuery = sprintf('%s WHERE (%s)', $subQuery, $where);
        }

        if ($hasGroup) {
            $qb = $this->connection->createQueryBuilder();
            $selectedJoinFields = [];
            /** @var DataSetInterface $dataSet */
            foreach ($dataSets as $dataSetIndex => $dataSet) {
                $qb = $this->buildSelectQuery($isAggregateAll, $aggregationFields, $qb, $dataSet, null, $joinConfig, $selectedJoinFields, true, [], $timezone);
            }

            foreach ($newFields as $newField) {
                if (($isAggregateAll || in_array($newField, $aggregationFields)) && $hasGroup && in_array($types[$newField], [FieldType::NUMBER, FieldType::DECIMAL])) {
                    $qb->addSelect(sprintf('SUM(%s) as %s', $this->connection->quoteIdentifier($newField), $this->connection->quoteIdentifier($newField)));
                    continue;
                }

                $qb->addSelect($this->connection->quoteIdentifier($newField));
            }

            $qb->from("($subQuery)", "sub1");
            $qb = $this->addGroupByQuery($qb, $transforms, $types);
            $subQuery = $qb->getSQL();
        }

        $outerQb = $this->connection->createQueryBuilder();

        $newFieldsOnPost = [];
        foreach ($transforms as $transform) {
            if (!$transform instanceof TransformInterface) {
                continue;
            }

            if (!$transform->getIsPostGroup()) {
                continue;
            }

            if ($transform instanceof AddCalculatedFieldTransform) {
                $outerQb = $this->addCalculatedFieldTransformQuery($outerQb, $transform, $newFieldsOnPost, $dataSetIndexes, $joinConfig, $removeSuffix = false);
                continue;
            }

            if ($transform instanceof AddFieldTransform) {
                $outerQb = $this->addNewFieldTransformQuery($outerQb, $transform, $newFieldsOnPost, $dataSetIndexes, $joinConfig);
                continue;
            }

            if ($transform instanceof AddConditionValueTransform) {
                // patch values for AddConditionValueTransform
                $transform = $this->patchAddConditionValueTransform($transform);

                $outerQb = $this->addConditionValueTransformQuery($outerQb, $transform, $newFieldsOnPost, $dataSetIndexes, $joinConfig, $removeSuffix = true);
                continue;
            }

            if ($transform instanceof ComparisonPercentTransform) {
                $outerQb = $this->addComparisonPercentTransformQuery($outerQb, $transform, $newFieldsOnPost, $dataSetIndexes, $removeSuffix = false);
                continue;
            }
        }

        $selectedJoinFields = [];
        /** @var DataSetInterface $dataSet */
        foreach ($dataSets as $dataSetIndex => $dataSet) {
            $outerQb = $this->buildSelectQuery($isAggregateAll, $aggregationFields, $outerQb, $dataSet, null, $joinConfig, $selectedJoinFields, false, $postAggregationFields, $timezone);
        }

        foreach ($newFields as $newField) {
            if (in_array($newField, $postAggregationFields)) {
                continue;
            }
            $outerQb->addSelect($this->connection->quoteIdentifier($newField));
        }

        $outerQb->from("($subQuery)", "sub2");

        $finalQb = $this->connection->createQueryBuilder();
        $finalQb->select("*");
        $fromQuery = $outerQb->getSQL();
        $finalQb->from("($fromQuery)", "sub2");

        $grouperQuery = $finalQb->getSQL();

        $finalQb = $this->applyFiltersForMultiDataSets($finalQb, $dataSets, $searches, $overridingFilters, $types, $joinConfig);

        $totalRowsQuery = $finalQb->getSQL();
        $totalRowsQb = $this->connection->createQueryBuilder();
        $totalRowsQb->select("count(*)");
        $totalRowsQb->from("($totalRowsQuery)", "sub3");
        $totalRowsQb->addOrderBy('NULL');

        /** Not call build filters because we don't have any column because of count(*) */
        $allFilters = $this->getFiltersForMultiDataSets($dataSets, $searches, $overridingFilters, $types, $joinConfig);
        $totalRowsQb = $this->bindFilterParam($totalRowsQb, $allFilters);

        $finalQb = $this->addSortQuery($finalQb, $transforms, $sortField, $sortDirection);
        if (empty($sortField) && !$hasSort) {
            // add ORDER BY NULL for performance
            $finalQb->addOrderBy('NULL');
        }
        $finalQb = $this->addLimitQuery($finalQb, $page, $limit);

        $rows = new SplDoublyLinkedList();
        $total = 0;

        try {
            $stmt = $finalQb->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $rows->push($row);
            }

            $stmtAllRows = $totalRowsQb->execute();
            while ($row = $stmtAllRows->fetch(\PDO::FETCH_ASSOC)) {
                $total = reset($row);
                break;
            }
        } catch (\Exception $e) {
            throw new PublicSimpleException('You have an error in your SQL syntax when run report. Please recheck report view');
        }

        return array(
            self::SUB_QUERY => $grouperQuery,
            self::STATEMENT_KEY => $stmt,
            self::DATE_RANGE_KEY => $dateRange,
            self::ROWS => $rows,
            self::TOTAl_ROWS => $total
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

        $types = [];
        $newFieldsTransform = [];
        foreach ($transforms as $transform) {
            if ($transform instanceof NewFieldTransform) {
                $newFieldsTransform[] = $transform->getFieldName();
                $types[$transform->getFieldName()] = $transform->getType();
            }
        }

        $qb = $this->connection->createQueryBuilder();

        // add select clause
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

        $qb = $this->applyFiltersForMultiDataSets($qb, $dataSets, $searches, $overridingFilters, $types, $joinConfig);

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
     * @param $aggregateAll
     * @param array $aggregationFields
     * @param QueryBuilder $qb
     * @param DataSetInterface $dataSet
     * @param $dataSetIndex
     * @param array $joinConfig
     * @param array $selectedJoinFields
     * @param bool $hasGroup
     * @param array $postAggregationFields
     * @param string $timezone
     * @return QueryBuilder
     * @internal param ParamsInterface $params
     * @internal param $hasGroup
     */
    protected function buildSelectQuery($aggregateAll, array $aggregationFields, QueryBuilder $qb, DataSetInterface $dataSet, $dataSetIndex,
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

            if (array_key_exists($field, $types) && in_array($types[$field], [FieldType::DECIMAL, FieldType::NUMBER]) && $hasGroup && $aggregateAll) {
                $qb->addSelect(sprintf('SUM(%s) as `%s`', $fieldName, $alias));
                continue;
            }

            if (in_array($fieldWithId, $aggregationFields) && $hasGroup) {
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
     * @return array
     */
    protected function buildFilters(array $filters, $tableAlias = null, $dataSetId = null)
    {
        $sqlConditions = [];
        $dateRanges = [];

        foreach ($filters as $key => $filter) {
            if (!$filter instanceof FilterInterface) {
                continue;
            }

            if ($dataSetId !== null) {
                $filter->trimTrailingAlias($dataSetId);
            }

            if ($filter instanceof DateFilterInterface) {
                $dateRanges[] = new DateRange($filter->getStartDate(), $filter->getEndDate());
            }

            $sqlConditions[] = $this->buildSingleFilter($filter, $tableAlias, $dataSetId, $key);
        }

        return array(
            self::CONDITION_KEY => $sqlConditions,
            self::DATE_RANGE_KEY => $dateRanges
        );
    }

    /**
     * @param FilterInterface $filter
     * @param null $tableAlias
     * @param null $dataSetId
     * @param $key
     * @return string
     */
    protected function buildSingleFilter(FilterInterface $filter, $tableAlias = null, $dataSetId = null, $key)
    {
        if (!empty($dataSetId) && $this->exitColumnInTable($filter->getFieldName(), $dataSetId)) {
            $filterField = str_replace(" ", "", $filter->getFieldName()) . $key;
            $fieldName = $tableAlias !== null ? sprintf('%s.%s', $tableAlias, $filter->getFieldName()) : $filter->getFieldName();
            $fieldName = empty($dataSetId) ? $this->connection->quoteIdentifier($fieldName) : $this->connection->quoteIdentifier($fieldName. "_" . $dataSetId);
        } else {
            $fieldName = $this->connection->quoteIdentifier($filter->getFieldName());
            $filterField = str_replace(" ", "", $filter->getFieldName()) . $key;
        }
        if ($filter instanceof DateFilterInterface) {
            if (!$filter->getStartDate() || !$filter->getEndDate()) {
                throw new InvalidArgumentException('invalid date range of filter');
            }

            return sprintf('(%s BETWEEN %s AND %s)', $fieldName, sprintf(':startDate%d%d', $filterField, $dataSetId ?? 0), sprintf(':endDate%d%d', $filterField, $dataSetId ?? 0));
        }

        $bindParamName = sprintf(':%s%d%d', $filterField, $filterField, $dataSetId ?? 0);
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

    /**
     * @param $column
     * @param $dataSetId
     * @return bool
     */
    private function exitColumnInTable($column, $dataSetId)
    {
        $sync = $this->getSync();

        $table = $sync->getTable($sync->getDataSetImportTableName($dataSetId));

        if ($table instanceof Table) {
            return $table->hasColumn($column);
        }

        return false;
    }

    /**
     * @param AddConditionValueTransform $transform
     * @return AddConditionValueTransform
     */
    private function patchAddConditionValueTransform(AddConditionValueTransform $transform)
    {
        // skip if $transform is already patched
        if (is_array($transform->getMappedValues())) {
            return $transform;
        }

        $values = $transform->getValues();

        // get all AddConditionValues by ids in values
        if (!is_array($values)) {
            return $transform;
        }

        /** @var ReportViewAddConditionalTransformValueRepositoryInterface $reportViewAddConditionTransformValueManager */
        $reportViewAddConditionTransformValueManager = $this->em->getRepository(ReportViewAddConditionalTransformValue::class);

        $addConditionValues = []; // as new values
        foreach ($values as $id) {
            if (empty($id) || !is_numeric($id)) {
                continue;
            }

            $addConditionValue = $reportViewAddConditionTransformValueManager->find($id);
            if (!$addConditionValue instanceof ReportViewAddConditionalTransformValueInterface) {
                continue;
            }

            $addConditionValues[] = $addConditionValue;
        }

        // override values in $addConditionValueConfig by new values
        $transform->setMappedValues($addConditionValues);

        return $transform;
    }
}