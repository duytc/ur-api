<?php


namespace UR\Service\DataSet;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Params;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\PagerParam;
use UR\Service\ColumnUtilTrait;
use UR\Service\Report\SqlBuilder;

class DataMappingManager implements DataMappingManagerInterface
{
    use ColumnUtilTrait;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    /** @var Synchronizer */
    protected $synchronizer;

    /** @var SqlBuilder */
    protected $sqlBuilder;

    protected $privateFields = [
        DataSetInterface::MAPPING_IS_ASSOCIATED,
        DataSetInterface::MAPPING_IS_MAPPED,
        DataSetInterface::MAPPING_IS_IGNORED,
        DataSetInterface::MAPPING_IS_LEFT_SIDE,
        DataSetInterface::UNIQUE_ID_COLUMN,
        DataSetInterface::ID_COLUMN
    ];

    /**
     * DataMappingService constructor.
     * @param EntityManagerInterface $em
     * @param DataSetManagerInterface $dataSetManager
     * @param SqlBuilder $sqlBuilder
     */
    public function __construct(EntityManagerInterface $em, DataSetManagerInterface $dataSetManager, SqlBuilder $sqlBuilder)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->dataSetManager = $dataSetManager;
        $this->synchronizer = new Synchronizer($this->conn, new Comparator());
        $this->sqlBuilder = $sqlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function getRows(DataSetInterface $dataSet, PagerParam $param, $filters = [])
    {
        $metrics = $dataSet->getMetrics();
        $metrics[DataSetInterface::MAPPING_IS_ASSOCIATED] = 'number';
        $metrics[DataSetInterface::MAPPING_IS_MAPPED] = 'number';
        $metrics[DataSetInterface::MAPPING_IS_IGNORED] = 'number';
        $metrics[DataSetInterface::MAPPING_IS_LEFT_SIDE] = 'number';
        $metrics[DataSetInterface::UNIQUE_ID_COLUMN] = 'string';
        $metrics[DataSetInterface::ID_COLUMN] = 'number';

        $data = [
            DataSet::DATA_SET_ID_KEY => $dataSet->getId(),
            DataSet::DIMENSIONS_KEY => array_keys($dataSet->getDimensions()),
            DataSet::METRICS_KEY => array_keys($metrics),
            DataSet::FILTERS_KEY => $filters,
        ];

        $reportDataSet = new DataSet($data);
        $page = !empty($param->getPage()) ? intval($param->getPage()) : 1;
        $limit = !empty($param->getLimit()) ? intval($param->getLimit()) : 10;

        $reportParam = new Params();
        $reportParam->setDataSets([$reportDataSet]);
        $reportParam->setPage($page);
        $reportParam->setLimit($limit);
        $reportParam->setSortField($param->getSortField());
        $reportParam->setOrderBy($param->getSortDirection());

        $result = $this->sqlBuilder->buildQueryForSingleDataSet($reportParam);
        $stmt = $result[SqlBuilder::STATEMENT_KEY];

        $rows = $stmt->fetchAll();
        $rows = $this->removeDataSetIdFromFieldName($dataSet->getId(), $this->privateFields, $rows);
        $rows = $this->removeDataSetIdFromRows($dataSet->getId(), $rows);
        $rows = $this->removeHiddenFields($dataSet, $rows);

        $fieldTypes = $this->getFieldTypes($dataSet);
        $columns = $this->getColumns($fieldTypes);

        $paginationDTO = [
            'records' => $rows,
            'itemPerPage' => $limit,
            'currentPage' => $page,
            'fieldTypes' => $fieldTypes,
            'columns' => $columns
        ];


        /** @var Statement $counter */
        $counter = $this->sqlBuilder->buildGroupQueryForSingleDataSet($result[SqlBuilder::SUB_QUERY], $reportDataSet);
        $totalReport = 0;

        if ($counter instanceof Statement) {
            $rows = $counter->fetchAll();
            $count = count($rows);

            // has group transform
            if ($count > 1) {
                $totalReport = $count;
            } else {
                $total = $rows[0];
                $totalReport = intval($total['total']);
            }
        }

        $paginationDTO['totalRecord'] = $totalReport;

        return $paginationDTO;
    }

    /**
     * Remove Data Set Id from field name as
     * Example
     *         __is_associated_58 to __is_associated
     *         __is_ignored_58 to __is_ignored
     *         __is_left_side_58 to __is_left_side
     *         __unique_id_58 to __unique_id
     * @param $dataSetId
     * @param $fields
     * @param array $rows
     * @return array
     */
    private function removeDataSetIdFromFieldName($dataSetId, $fields, array $rows)
    {
        foreach ($rows as &$row) {
            foreach ($fields as $field) {
                $fieldWithDataSetId = sprintf('%s_%s', $field, $dataSetId);
                if (isset($row[$fieldWithDataSetId])) {
                    $row[$field] = $row[$fieldWithDataSetId];
                    unset($row[$fieldWithDataSetId]);
                }
            }
        }
        return $rows;
    }

    /**
     * Remove Data Set Id from rows
     * Example
     *         __is_associated_58 to __is_associated
     *         __is_ignored_58 to __is_ignored
     *         __is_left_side_58 to __is_left_side
     *         __unique_id_58 to __unique_id
     * @param $dataSetId
     * @param array $rows
     * @return array
     */
    private function removeDataSetIdFromRows($dataSetId, array $rows)
    {
        $endWith = sprintf('_%s', $dataSetId);
        foreach ($rows as &$row) {
            foreach ($row as $field => $value) {
                $pos = strpos($field, $endWith);
                if ($pos != false) {
                    $normalizeField = substr($field, 0, $pos);
                    $row[$normalizeField] = $value;
                    unset($row[$field]);
                }
            }
        }
        return $rows;
    }

    /**
     * Remove hidden fields as __date_day, __date_month, __date_year
     *
     * @param DataSetInterface $dataSet
     * @param array $rows
     * @return array
     */
    private function removeHiddenFields(DataSetInterface $dataSet, array $rows)
    {
        $allDimensionMetrics = array_merge($dataSet->getMetrics(), $dataSet->getDimensions());
        $dateTimeFields = array_filter($allDimensionMetrics, function ($field) {
            return $field == FieldType::DATE || $field == FieldType::DATETIME;
        });

        if (count($dateTimeFields) < 1) {
            return $rows;
        }

        $temporaryFields = [];
        foreach ($dateTimeFields as $column) {
            $fieldPattern = "__%s_%s_%s";
            $temporaryFields[] = sprintf($fieldPattern, $column, "day", $dataSet->getId());
            $temporaryFields[] = sprintf($fieldPattern, $column, "month", $dataSet->getId());
            $temporaryFields[] = sprintf($fieldPattern, $column, "year", $dataSet->getId());
        }

        foreach ($rows as &$row) {
            foreach ($row as $key => $value) {
                if (!in_array($key, $temporaryFields)) {
                    continue;
                }
                unset($row[$key]);
            }
        }
        return $rows;
    }

    /**
     * Update fields in data set table by rowId
     *
     * @param DataSetInterface $dataSet
     * @param $rowId
     * @param array $params
     */
    public function updateRow(DataSetInterface $dataSet, $rowId, array $params)
    {
        try {
            $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSet->getId());
            $qb = $this->conn->createQueryBuilder();
            $qb->update($tableName)
                ->where($qb->expr()->eq(DataSetInterface::ID_COLUMN, $rowId));

            foreach ($params as $field => $value) {
                $qb
                    ->set($field, $value);
            }

            $qb->execute();
        } catch (Exception $e) {

        }
    }

    /**
     * @param DataSetInterface $reportDataSet
     * @return array
     */
    private function getFieldTypes(DataSetInterface $reportDataSet)
    {
        $allFields = array_merge($reportDataSet->getDimensions(), $reportDataSet->getMetrics());
        $fieldTypes = [];

        foreach ($allFields as $key => &$field) {
            if (in_array($key, $this->privateFields)) {
                $fieldTypes[$key] = $field;
                continue;
            }
            $fieldTypes[sprintf('%s', $key, $reportDataSet->getId())] = $field;
        }

        $fieldTypes[DataSetInterface::MAPPING_IS_ASSOCIATED] = FieldType::NUMBER;
        $fieldTypes[DataSetInterface::MAPPING_IS_MAPPED] = FieldType::NUMBER;
        $fieldTypes[DataSetInterface::MAPPING_IS_IGNORED] = FieldType::NUMBER;
        $fieldTypes[DataSetInterface::MAPPING_IS_LEFT_SIDE] = FieldType::NUMBER;
        $fieldTypes[DataSetInterface::UNIQUE_ID_COLUMN] = FieldType::TEXT;
        $fieldTypes[DataSetInterface::ID_COLUMN] = FieldType::NUMBER;

        return $fieldTypes;
    }

    /**
     * @return DataSetManagerInterface
     */
    protected function getDataSetManager()
    {
        return $this->dataSetManager;
    }

    /**
     * @param array $fieldTypes
     * @return array
     */
    private function getColumns(array $fieldTypes)
    {
        $columns = [];
        foreach ($fieldTypes as $field => $type) {
            $columns[$field] = $this->convertColumn($field, true);
        }

        return $columns;
    }
}