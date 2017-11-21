<?php

namespace UR\Service\AutoOptimization;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\DataSet\FieldType;

class DataTrainingTableService
{
    const DATA_TRAINING_TABLE_NAME_PREFIX_TEMPLATE = '__data_training_%d'; // %d is report view id

    const COLUMN_ID = '__id';

    const FIELD_LENGTH_LARGE_TEXT = 65535;
    const FIELD_LENGTH_TEXT = 512;

    /** @var Connection */
    protected $conn;

    /** @var Comparator */
    protected $comparator;

    public function __construct(Connection $conn, Comparator $comparator)
    {
        $this->conn = $conn;
        $this->comparator = $comparator;
    }

    /**
     * @return Connection
     */
    public function getConn()
    {
        return $this->conn;
    }

    /**
     * 1
     *
     * Synchronize the schema with the database
     *
     * @param Schema $schema
     * @return $this
     * @throws DBALException
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
     * 1
     *
     * Get query: Synchronize the schema with the database
     *
     * @param Schema $schema
     * @return $this
     */
    public function getSyncSchemaQuery(Schema $schema)
    {
        $sm = $this->conn->getSchemaManager();
        $fromSchema = $sm->createSchema();

        $schemaDiff = $this->comparator->compare($fromSchema, $schema);

        $saveQueries = $schemaDiff->toSaveSql($this->conn->getDatabasePlatform());

        return $saveQueries;
    }

    /**
     * 1
     *
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return Table|false
     */
    public function createEmptyDataTrainingTable(AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        /* check if data import table existed */
        $table = $this->getDataTrainingTable($autoOptimizationConfig->getId());

        if ($table instanceof Table) {
            return $table; // existed => return current table
        }

        // not existed => create new
        $schema = new Schema();

        /* create data import table with hidden fields */
        // create and add hidden fields
        $dataTrainingTableName = self::getDataTrainingTableName($autoOptimizationConfig->getId());
        $dataTrainingTable = $schema->createTable($dataTrainingTableName);
        $dataTrainingTable->addColumn(self::COLUMN_ID, Type::INTEGER, array('autoincrement' => true, 'unsigned' => true));
        $dataTrainingTable->setPrimaryKey(array(self::COLUMN_ID));

        // add dimensions
        foreach ($autoOptimizationConfig->getDimensions() as $fieldName => $fieldType) {
            if ($fieldType === FieldType::NUMBER) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::DECIMAL) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::LARGE_TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_LARGE_TEXT]);
            } else if ($fieldType === FieldType::TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_TEXT]);
            } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
            } else {
                $dataTrainingTable->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);
            }
        }

        // add metrics
        foreach ($autoOptimizationConfig->getMetrics() as $fieldName => $fieldType) {
            if ($fieldType === FieldType::NUMBER) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::DECIMAL) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::LARGE_TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_LARGE_TEXT]);
            } else if ($fieldType === FieldType::TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_TEXT]);
            } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
            } else {
                $dataTrainingTable->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);
            }
        }

        //// create table
        try {
            // sync schema
            $this->syncSchema($schema);

            $this->conn->beginTransaction();
            $this->conn->commit();

            // truncate table
            $truncateSql = $this->conn->getDatabasePlatform()->getTruncateTableSQL(self::getDataTrainingTableName($autoOptimizationConfig->getId()));
            $this->conn->exec($truncateSql);
        } catch (\Exception $e) {
            return false;
        }

        return $dataTrainingTable;
    }

    /**
     * 1
     *
     * @param int $id
     * @return Table|false
     */
    public function getDataTrainingTable($id)
    {
        $tableName = self::getDataTrainingTableName($id);

        return $this->getTable($tableName);
    }

    /**
     * 1
     *
     * @param int $id
     * @return false
     */
    public function deleteDataTrainingTable($id)
    {
        $sm = $this->conn->getSchemaManager();

        $tableName = self::getDataTrainingTableName($id);

        if (!$sm->tablesExist([$tableName])) {
            return false;
        }

        $sm->dropTable($tableName);

        return true;
    }

    /**
     * 1
     *
     * get Data Training Table Name
     *
     * @param int $autoOptimizationConfigId
     * @return string
     */
    public static function getDataTrainingTableName($autoOptimizationConfigId)
    {
        return sprintf(self::DATA_TRAINING_TABLE_NAME_PREFIX_TEMPLATE, $autoOptimizationConfigId);
    }

    /**
     * 1
     *
     * alter Type For Column
     *
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

        self::prepareStatementAlterColumnType($conn, $columnName, $dataTable->getName(), $columnType, $columnLength);

        return true;
    }

    /**
     * 1
     *
     * prepare Statement Alter Column Type
     * @param Connection $conn
     * @param string $columnName
     * @param string $tableName
     * @param string $columnType
     * @param null|int $columnLength
     * @throws DBALException
     */
    public static function prepareStatementAlterColumnType(Connection $conn, $columnName, $tableName, $columnType, $columnLength = null)
    {
        $updateSql = self::alterColumnTypeSql($columnName, $tableName, $columnType, $columnLength);
        $stmtCreateIndex = $conn->prepare($updateSql);
        $stmtCreateIndex->execute();
    }

    /**
     * 1
     *
     * @param $columnName
     * @param $tableName
     * @param string $columnType
     * @param null $columnLength
     * @return string sql drop index
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
}