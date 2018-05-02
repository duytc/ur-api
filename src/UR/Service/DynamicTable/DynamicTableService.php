<?php

namespace UR\Service\DynamicTable;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Exception;
use UR\Service\DataSet\FieldType;

class DynamicTableService implements DynamicTableServiceInterface
{
    const FIELD_LENGTH_LARGE_TEXT = 65535;
    const FIELD_LENGTH_TEXT = 512;

    private $conn;
    private $batchSize;
    private $comparator;
    private $em;

    /**
     * DynamicTableService constructor.
     * @param EntityManagerInterface $em
     * @param int $batchSize
     */
    public function __construct(EntityManagerInterface $em, $batchSize = 5000)
    {
        $this->em = $em;
        $this->batchSize = $batchSize;
        $this->comparator = new Comparator();
        $this->conn = $em->getConnection();
    }

    /**
     * @param Connection $conn
     * @param Table $dataTable
     * @param $columnName
     * @param $columnType
     * @return bool
     */
    public static function alterTypeForColumn(Connection $conn, Table $dataTable, $columnName, $columnType)
    {
        if (!$dataTable->hasColumn($columnName)) {
            return false;
        }

        // set field length if text of longtext
        $columnLength = $columnType === FieldType::LARGE_TEXT
            ? self::FIELD_LENGTH_LARGE_TEXT
            : ($columnType === FieldType::TEXT
                ? self::FIELD_LENGTH_TEXT
                : null // other types: not set length
            );

        try {
            self::prepareStatementAlterColumnType($conn, $columnName, $dataTable->getName(), $columnType, $columnLength);
        } catch (Exception $e) {

        }

        return true;
    }

    /**
     * @param Connection $conn
     * @param $columnName
     * @param $tableName
     * @param $columnType
     * @param null $columnLength
     */
    public static function prepareStatementAlterColumnType(Connection $conn, $columnName, $tableName, $columnType, $columnLength = null)
    {
        $updateSql = self::alterColumnTypeSql($columnName, $tableName, $columnType, $columnLength);
        $stmtCreateIndex = $conn->prepare($updateSql);
        $stmtCreateIndex->execute();
    }

    /**
     * @param $columnName
     * @param $tableName
     * @param string $columnType
     * @param null $columnLength
     * @return string
     */
    public static function alterColumnTypeSql($columnName, $tableName, $columnType, $columnLength = null)
    {
        // set change columnType to native type of sql
        $columnType = $columnType === FieldType::LARGE_TEXT
            ? 'varchar'
            : ($columnType === FieldType::TEXT
                ? 'varchar'
                : $columnType
            );

        if (is_integer($columnLength) && $columnLength > 0) {
            // append length to columnType
            $columnType = sprintf('%s(%d)', $columnType, $columnLength);
        }

        return sprintf('ALTER TABLE %s MODIFY %s %s;',
            $tableName,
            $columnName,
            $columnType
        );
    }

    /**
     * @return mixed
     */
    public function getBatchSize()
    {
        return $this->batchSize;
    }

    /**
     * @inheritdoc
     */
    public function createEmptyTable($tableName, array $fields)
    {
        /* check if data import table existed */
        $table = $this->getTable($tableName);

        if ($table instanceof Table) {
            $this->deleteTable($tableName);
        }

        // not existed => create new
        $schema = new Schema();
        $newTable = $schema->createTable($tableName);
        $newTable->addColumn(self::COLUMN_ID, Type::INTEGER, array('autoincrement' => true, 'unsigned' => true));
        $newTable->setPrimaryKey(array(self::COLUMN_ID));

        // get all columns
        $allFields = [];
        array_walk($fields, function ($fieldType, &$fieldName) use (&$allFields) {
            $fieldName = str_replace(' ', '_', strtolower($fieldName));
            $allFields[$fieldName] = $fieldType;
        });

        foreach ($allFields as $fieldName => $fieldType) {
            if ($newTable->hasColumn($fieldName)) {
                continue;
            }
            
            $newTable = $this->addFieldForTable($newTable, $fieldName, $fieldType);
        }
        // alter table add columns
        try {
            // sync schema
            $this->syncSchema($schema);

            $this->getConn()->beginTransaction();
            $this->getConn()->commit();

            // truncate table
            $truncateSql = $this->getConn()->getDatabasePlatform()->getTruncateTableSQL($tableName);
            $this->getConn()->exec($truncateSql);
        } catch (Exception $e) {
            return false;
        }

        return $newTable;
    }

    /**
     * 1
     *
     * @param $tableName
     * @return Table|false
     */
    public function getTable($tableName)
    {
        $sm = $this->conn->getSchemaManager();

        if (!$sm->tablesExist([$tableName])) {
            return false;
        }

        return $sm->listTableDetails($tableName);
    }

    /**
     * @param $tableName
     * @return bool
     */
    public function deleteTable($tableName)
    {
        $sm = $this->conn->getSchemaManager();

        if (!$sm->tablesExist([$tableName])) {
            return false;
        }

        try {
            $sm->dropTable($tableName);
        } catch (Exception $e) {

        }

        return true;
    }

    /**
     * @param Table $tableName
     * @param $fieldName
     * @param $fieldType
     * @return Table
     */
    public function addFieldForTable(Table $tableName, $fieldName, $fieldType)
    {
        if (empty($fieldType)) {
            return $tableName;
        }

        $fieldName = $this->em->getConnection()->quoteIdentifier($fieldName);

        if ($fieldType === FieldType::NUMBER) {
            $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
            $tableName->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
        } else if ($fieldType === FieldType::DECIMAL) {
            $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
            $tableName->addColumn($fieldName, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
        } else if ($fieldType === FieldType::LARGE_TEXT) {
            $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
            $tableName->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_LARGE_TEXT]);
        } else if ($fieldType === FieldType::TEXT) {
            $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
            $tableName->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_TEXT]);
        } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
            $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
            $tableName->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
        } else {
            $tableName->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);
        }

        return $tableName;
    }

    /**
     * @inheritdoc
     */
    public function syncSchema(Schema $schema)
    {
        $saveQueries = $this->getSyncSchemaQuery($schema);

        foreach ($saveQueries as $sql) {
            $this->conn->exec($sql);
        }

        return $this;
    }

    /**
     * @param Schema $schema
     * @return array
     */
    private function getSyncSchemaQuery(Schema $schema)
    {
        $sm = $this->conn->getSchemaManager();
        $fromSchema = $sm->createSchema();

        $schemaDiff = $this->comparator->compare($fromSchema, $schema);

        $saveQueries = $schemaDiff->toSaveSql($this->conn->getDatabasePlatform());

        return $saveQueries;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConn()
    {
        return $this->conn;
    }

    /**
     * @param $tableName
     * @param $whereClause
     * @return array
     */
    public function selectRows($tableName, $whereClause)
    {
        $rows = [];

        $selectSql = sprintf('SELECT * FROM %s %s;', $tableName, $whereClause);

        try {
            $rows = $this->getConn()->executeQuery($selectSql)->fetchAll();
            foreach ($rows as $key => $row) {
                if (!is_array($row)) {
                    unset($rows[$key]);
                }
            }
        } catch (Exception $e) {
            return $rows;

        } catch (DBALException $e) {
            return $rows;
        }

        return $rows;
    }

    /**
     * @param $tableName
     * @param $column
     * @param $whereClause
     * @return array
     */
    public function selectDistinctOneColumns($tableName, $column, $whereClause =null)
    {
        if (!empty($whereClause)) {
            $selectSql = sprintf("SELECT DISTINCT(%s) FROM %s %s;", $column, $tableName, $whereClause);
        } else {
            $selectSql = sprintf("SELECT DISTINCT(%s) FROM %s;", $column, $tableName);
        }

        $values = [];
        try {
            $rows = $this->getConn()->executeQuery($selectSql)->fetchAll();
            foreach ($rows as $row) {
                if (!is_array($row) || !array_key_exists($column, $row) || empty($row[$column])) {
                    continue;
                }
                $values[] = $row[$column];
            }
        } catch (Exception $e) {

        } catch (DBALException $exception) {

        }

        return $values;
    }

    public function insertDataToTable($tableName, $columns, $questionMarks, $insertValues)
    {
        $this->conn->beginTransaction();
        try {
            $this->executeInsert($tableName, $columns, $questionMarks, $insertValues);
        } catch (DBALException $e) {

        }

        //commit update and insert
        try {
            $this->conn->commit();
        } catch (ConnectionException $e) {
        }
        $this->conn->close();
    }

    /**
     * @param $tableName
     * @param array $columns
     * @param array $question_marks
     * @param array $insert_values
     * @throws DBALException
     */
    private function executeInsert($tableName, array $columns, array $question_marks, array $insert_values)
    {
        $insertSql = sprintf("INSERT INTO %s (`%s`) VALUES %s",
            $tableName,
            implode("`,`", array_keys($columns)),
            implode(',', $question_marks)
        );

        try {
            $stmt = $this->conn->prepare($insertSql);
            $stmt->execute($insert_values);
        } catch (Exception $e) {

        }
    }

    /**
     * @return bool|void
     */
    public function rollBack()
    {
        try {
            $this->getConn()->rollBack();
        } catch (ConnectionException $e) {
        }
    }

    /**
     *
     */
    public function clear()
    {
        $this->getEntityManager()->clear();
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @inheritdoc
     */
    public function getAllValuesOfOneColumn($tableName, $columnName)
    {
        $allUniqueValues = [];

        $table = $this->getTable($tableName);
        if (!$table instanceof Table) {
            return [];
        }

        $columns = $table->getColumns();
        if (!in_array($columnName, array_keys($columns))) {
            return [];
        }

        $selectSql = sprintf("SELECT DISTINCT(%s) FROM %s;",
            $columnName,
            $table->getName()
        );

        try {
            $allUniqueValues = $this->conn->executeQuery($selectSql)->fetchAll();
        } catch (Exception $e) {

        }

        $allUniqueValues = $this->arrayFlatten($allUniqueValues);

        return $allUniqueValues;
    }

    /**
     * @param array $array
     * @return array
     */
    private function arrayFlatten(array $array)
    {
        $flatten = array();
        array_walk_recursive($array, function ($value) use (&$flatten) {
            $flatten[] = $value;
        });

        return $flatten;
    }
}