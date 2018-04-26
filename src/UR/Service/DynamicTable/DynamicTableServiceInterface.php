<?php

namespace UR\Service\DynamicTable;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;

interface DynamicTableServiceInterface
{
    const COLUMN_ID = '__id';

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConn();

    /**
     * @param Connection $conn
     * @param Table $dataTable
     * @param $columnName
     * @param $columnType
     * @return bool
     */
    public static function alterTypeForColumn(Connection $conn, Table $dataTable, $columnName, $columnType);

    /**
     * @inheritdoc
     */
    public function createEmptyTable($tableName, array $fields);

    /**
     * 1
     *
     * @param $tableName
     * @return Table|false
     */
    public function getTable($tableName);

    /**
     * @param Table $tableName
     * @param $fieldName
     * @param $fieldType
     * @return Table
     */
    public function addFieldForTable(Table $tableName, $fieldName, $fieldType);

    /**
     * @param $tableName
     * @return bool
     */
    public function deleteTable($tableName);

    /**
     * @param $tableName
     * @param $whereClause
     * @return array
     */
    public function selectRows($tableName, $whereClause);

    /**
     * @param $tableName
     * @param $columns
     * @param $questionMarks
     * @param $insertValues
     * @return mixed
     */
    public function insertDataToTable($tableName, $columns, $questionMarks, $insertValues);

    /**
     * @return mixed
     */
    public function rollBack();

    /**
     * @return mixed
     */
    public function clear();

    /**
     * @return mixed
     */
    public function getBatchSize();

    /**
     * @inheritdoc
     */
    public function getAllValuesOfOneColumn($tableName, $columnName);

    public function selectDistinctOneColumns($tableName, $column, $whereClause = null);

    /**
     * @inheritdoc
     */
    public function syncSchema(Schema $schema);
}