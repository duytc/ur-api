<?php

namespace UR\Service\DataSet;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use UR\Model\Core\DataSetInterface;

class Synchronizer
{
    const DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE = '__data_import_%d'; // %d is data set id
    const HIDDEN_FIELD_MONTH_TEMPLATE = '__%s_month';
    const HIDDEN_FIELD_YEAR_TEMPLATE = '__%s_year';
    const HIDDEN_FIELD_DAY_TEMPLATE = '__%s_day';

    const FIELD_LENGTH_LONGTEXT = 65535;
    const FIELD_LENGTH_TEXT = 2048;

    const DATA_IMPORT_TABLE_INDEX_PREFIX_TEMPLATE = '%s_index_%s'; // %s is data import table name, %s is field name
    const REQUIRED_INDEXES = ['primary', 'PRIMARY', 'unique_hash_idx'];

    /**
     * @var Connection
     */
    protected $conn;
    /**
     * @var Comparator
     */
    protected $comparator;

    public function __construct(Connection $conn, Comparator $comparator)
    {
        $this->conn = $conn;
        $this->comparator = $comparator;
    }

    /**
     * Synchronize the schema with the database
     *
     * @param Schema $schema
     * @return $this
     * @throws DBALException
     */
    public function syncSchema(Schema $schema)
    {
        $sm = $this->conn->getSchemaManager();
        $fromSchema = $sm->createSchema();

        $schemaDiff = $this->comparator->compare($fromSchema, $schema);

        $saveQueries = $schemaDiff->toSaveSql($this->conn->getDatabasePlatform());

        foreach ($saveQueries as $sql) {
            $this->conn->exec($sql);
        }

        return $this;
    }

    public function createEmptyDataSetTable(DataSetInterface $dataSet)
    {
        /* check if data import table existed */
        $table = $this->getDataSetImportTable($dataSet->getId());

        if ($table instanceof Table) {
            return $table;
        }

        $schema = new Schema();

        /* create data import table with hidden fields */
        // create and add hidden fields
        $dataSetImportTableName = self::getDataSetImportTableName($dataSet->getId());
        $dataSetImportTable = $schema->createTable($dataSetImportTableName);
        $dataSetImportTable->addColumn(DataSetInterface::ID_COLUMN, Type::INTEGER, array('autoincrement' => true, 'unsigned' => true));
        $dataSetImportTable->setPrimaryKey(array(DataSetInterface::ID_COLUMN));
        $dataSetImportTable->addColumn(DataSetInterface::DATA_SOURCE_ID_COLUMN, Type::INTEGER, array('unsigned' => true, 'notnull' => true));
        $dataSetImportTable->addColumn(DataSetInterface::IMPORT_ID_COLUMN, Type::INTEGER, array('unsigned' => true, 'notnull' => true));
        $dataSetImportTable->addColumn(DataSetInterface::UNIQUE_ID_COLUMN, FieldType::TEXT, array('notnull' => true));
        $dataSetImportTable->addColumn(DataSetInterface::OVERWRITE_DATE, FieldType::DATETIME, array('notnull' => false, 'default' => null));

        // add dimensions
        foreach ($dataSet->getDimensions() as $fieldName => $fieldType) {
            $col = $dataSetImportTable->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);

            if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                // add month and year also
                $dataSetImportTable->addColumn(self::getHiddenColumnDay($fieldName), Type::INTEGER, ['notnull' => false, 'default' => null]);

                $dataSetImportTable->addColumn(self::getHiddenColumnMonth($fieldName), Type::INTEGER, ['notnull' => false, 'default' => null]);

                $dataSetImportTable->addColumn(self::getHiddenColumnYear($fieldName), Type::INTEGER, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::MULTI_LINE_TEXT) {
                $col->setLength(self::FIELD_LENGTH_LONGTEXT);
            } else if ($fieldType === FieldType::TEXT) {
                $col->setLength(self::FIELD_LENGTH_TEXT);
            }
        }

        // add metrics
        foreach ($dataSet->getMetrics() as $fieldName => $fieldType) {
            if ($fieldType === FieldType::NUMBER) {
                $dataSetImportTable->addColumn($fieldName, 'integer', ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::DECIMAL) {
                $dataSetImportTable->addColumn($fieldName, $fieldType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::MULTI_LINE_TEXT) {
                $dataSetImportTable->addColumn($fieldName, Type::TEXT, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::TEXT) {
                $dataSetImportTable->addColumn($fieldName, Type::TEXT, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_TEXT]);
            } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $dataSetImportTable->addColumn($fieldName, FieldType::DATE, ['notnull' => false, 'default' => null]);
                // add month and year also
                $dataSetImportTable->addColumn(sprintf(self::HIDDEN_FIELD_DAY_TEMPLATE, $fieldName), Type::INTEGER, ['notnull' => false, 'default' => null]);
                $dataSetImportTable->addColumn(sprintf(self::HIDDEN_FIELD_MONTH_TEMPLATE, $fieldName), Type::INTEGER, ['notnull' => false, 'default' => null]);
                $dataSetImportTable->addColumn(sprintf(self::HIDDEN_FIELD_YEAR_TEMPLATE, $fieldName), Type::INTEGER, ['notnull' => false, 'default' => null]);
            } else {
                $dataSetImportTable->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);
            }
        }

        //// create table
        try {
            // sync schema
            $this->syncSchema($schema);

            // TODO: remove if not need commit here
            $this->conn->beginTransaction();
            $this->conn->commit();

            // update indexes
            self::updateIndexes($this->conn, $dataSetImportTable, $dataSet);

            // truncate table
            $truncateSql = $this->conn->getDatabasePlatform()->getTruncateTableSQL(self::getDataSetImportTableName($dataSet->getId()));
            $this->conn->exec($truncateSql);
        } catch (\Exception $e) {
            throw new \mysqli_sql_exception(sprintf('Cannot Sync Schema %s, exception: ', $schema->getName(), $e->getMessage()));
        }

        return $dataSetImportTable;
    }

    /**
     * @param int $id
     * @return Table|false
     */
    public function getDataSetImportTable($id)
    {
        $sm = $this->conn->getSchemaManager();

        $tableName = self::getDataSetImportTableName($id);

        if (!$sm->tablesExist([$tableName])) {
            return false;
        }

        return $sm->listTableDetails($tableName);
    }

    /**
     * get DataSet Import Table Name
     *
     * @param int $dataSetId
     * @return string
     */
    public static function getDataSetImportTableName($dataSetId)
    {
        return sprintf(self::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSetId);
    }

    /**
     * get Hidden Column Day from a date column
     *
     * e.g: date column = dates => hidden column day = __dates_day
     *
     * @param string $dateColumnName
     * @return string
     */
    public static function getHiddenColumnDay($dateColumnName)
    {
        return sprintf(self::HIDDEN_FIELD_DAY_TEMPLATE, $dateColumnName);
    }

    /**
     * get Hidden Column Month from a date column
     *
     * e.g: date column = dates => hidden column month = __dates_month
     *
     * @param string $dateColumnName
     * @return string
     */
    public static function getHiddenColumnMonth($dateColumnName)
    {
        return sprintf(self::HIDDEN_FIELD_MONTH_TEMPLATE, $dateColumnName);
    }

    /**
     * get Hidden Column Year from a date column
     *
     * e.g: date column = dates => hidden column year = __dates_year
     *
     * @param string $dateColumnName
     * @return string
     */
    public static function getHiddenColumnYear($dateColumnName)
    {
        return sprintf(self::HIDDEN_FIELD_YEAR_TEMPLATE, $dateColumnName);
    }

    /**
     * update Indexes: create if index does not exist. Also remove non existing indexes.
     *
     * @param Connection $conn
     * @param Table $dataSetImportTable
     * @param DataSetInterface $dataSet
     * @param int $removedIndexesCount
     * @return int number of created indexes
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public static function updateIndexes(Connection $conn, Table $dataSetImportTable, DataSetInterface $dataSet, &$removedIndexesCount = 0)
    {
        $inUsedIndexes = [];

        // add indexes for hidden fields
        /*
         * all dimensions To Be Created Indexes
         * using this array to temporary store for batch execute sql "create index"
         * We cannot use $dataSetImportTable->addIndex() directly because this does not support set length for text field
         *
         * format:
         * [
         *     ['fieldName' => <column name>, 'fieldType' => <column type>]
         * ];
         */
        $dimensionsToBeCreatedIndexes = [
            [
                'fieldName' => DataSetInterface::DATA_SOURCE_ID_COLUMN,
                'fieldType' => Type::INTEGER
            ],
            [
                'fieldName' => DataSetInterface::IMPORT_ID_COLUMN,
                'fieldType' => Type::INTEGER
            ],
            [
                'fieldName' => DataSetInterface::OVERWRITE_DATE,
                'fieldType' => Type::INTEGER
            ]
        ];

        // add dimensions, also add indexes for all dimensions
        foreach ($dataSet->getDimensions() as $fieldName => $fieldType) {
            // add index for column
            $dimensionsToBeCreatedIndexes[] = [
                'fieldName' => $fieldName,
                'fieldType' => $fieldType
            ];

            // add indexes for hidden columns day/month/year if this column type is date|datetime
            if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $hiddenDayColumn = sprintf(self::HIDDEN_FIELD_DAY_TEMPLATE, $fieldName);
                $dimensionsToBeCreatedIndexes[] = [
                    'fieldName' => $hiddenDayColumn,
                    'fieldType' => Type::INTEGER
                ];

                $hiddenMonthColumn = sprintf(self::HIDDEN_FIELD_MONTH_TEMPLATE, $fieldName);
                $dimensionsToBeCreatedIndexes[] = [
                    'fieldName' => $hiddenMonthColumn,
                    'fieldType' => Type::INTEGER
                ];

                $hiddenYearColumn = sprintf(self::HIDDEN_FIELD_YEAR_TEMPLATE, $fieldName);
                $dimensionsToBeCreatedIndexes[] = [
                    'fieldName' => $hiddenYearColumn,
                    'fieldType' => Type::INTEGER
                ];
            }
        }

        $createdIndexesCount = 0;

        // execute prepared statement for creating indexes
        $conn->beginTransaction();

        foreach ($dimensionsToBeCreatedIndexes as $dimensionsToBeCreatedIndex) {
            if (!array_key_exists('fieldName', $dimensionsToBeCreatedIndex)
                && !array_key_exists('fieldType', $dimensionsToBeCreatedIndex)
            ) {
                continue;
            }

            $columnName = $dimensionsToBeCreatedIndex['fieldName'];
            $columnType = $dimensionsToBeCreatedIndex['fieldType'];

            $indexName = self::getDataSetImportTableIndexName($dataSetImportTable->getName(), $columnName);

            // update inUsedIndexes
            $inUsedIndexes[] = $indexName;

            if (!$dataSetImportTable->hasColumn($columnName) || $dataSetImportTable->hasIndex($indexName)) {
                continue; // column not found or already has index
            }

            $createdIndexesCount++;

            // set field length if text of longtext
            $fieldLength = $columnType === FieldType::MULTI_LINE_TEXT
                ? self::FIELD_LENGTH_LONGTEXT
                : ($columnType === FieldType::TEXT
                    ? self::FIELD_LENGTH_TEXT
                    : null // other types: not set length
                );

            self::prepareStatementCreateIndex($conn, $indexName, $dataSetImportTable->getName(), $columnName, $fieldLength);
        }

        $conn->commit();

        // remove non existing indexes
        $conn->beginTransaction();

        $allIndexObjects = $dataSetImportTable->getIndexes();
        $allIndexes = array_map(function (Index $indexObject) {
            return $indexObject->getName();
        }, $allIndexObjects);

        $nonExistingIndexes = array_diff($allIndexes, $inUsedIndexes);
        foreach ($nonExistingIndexes as $nonExistingIndex) {
            // exclude 'primary' and 'unique_hash_idx' indexes
            if (in_array($nonExistingIndex, self::REQUIRED_INDEXES)) {
                continue;
            }

            // $dataSetImportTable->dropIndex($nonExistingIndex);
            self::prepareStatementDropIndex($conn, $nonExistingIndex, $dataSetImportTable->getName());

            $removedIndexesCount++;
        }

        $conn->commit();

        return $createdIndexesCount;
    }

    /**
     * get DataSet Import Table Index Name
     *
     * @param string $dataImportTableName
     * @param string $columnName
     * @return string
     */
    public static function getDataSetImportTableIndexName($dataImportTableName, $columnName)
    {
        return sprintf(self::DATA_IMPORT_TABLE_INDEX_PREFIX_TEMPLATE, $dataImportTableName, $columnName);
    }

    /**
     * @return Connection
     */
    public function getConn(): Connection
    {
        return $this->conn;
    }

    /**
     * prepare Statement Create Index
     * @param Connection $conn
     * @param string $indexName
     * @param string $tableName
     * @param string $fieldName
     * @param null|int $fieldLength
     * @throws DBALException
     */
    public static function prepareStatementCreateIndex(Connection $conn, $indexName, $tableName, $fieldName, $fieldLength = null)
    {
        $updateSql = self::createIndexSql($indexName, $tableName, $fieldName, $fieldLength);
        $stmtCreateIndex = $conn->prepare($updateSql);
        $stmtCreateIndex->execute();
    }

    /**
     * prepare Statement Drop Index
     * @param Connection $conn
     * @param string $indexName
     * @param string $tableName
     * @throws DBALException
     */
    public static function prepareStatementDropIndex(Connection $conn, $indexName, $tableName)
    {
        $updateSql = self::dropIndexSql($indexName, $tableName);
        $stmtCreateIndex = $conn->prepare($updateSql);
        $stmtCreateIndex->execute();
    }

    /**
     * @param $indexName
     * @param $tableName
     * @param $fieldName
     * @param null|int $fieldLength
     * @return string sql create index
     */
    public static function createIndexSql($indexName, $tableName, $fieldName, $fieldLength = null)
    {
        if (is_integer($fieldLength) && $fieldLength > 0) {
            // append length to fieldName
            $fieldName = sprintf('%s(%d)', $fieldName, Synchronizer::FIELD_LENGTH_LONGTEXT);
        }

        return sprintf('CREATE INDEX %s ON %s (%s)',
            $indexName,
            $tableName,
            $fieldName
        );
    }

    /**
     * @param $indexName
     * @param $tableName
     * @return string sql drop index
     */
    public static function dropIndexSql($indexName, $tableName)
    {
        return sprintf('ALTER TABLE %s DROP INDEX %s;',
            $tableName,
            $indexName
        );
    }
}