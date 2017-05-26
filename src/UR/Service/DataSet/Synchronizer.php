<?php

namespace UR\Service\DataSet;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use UR\Model\Core\DataSetInterface;
use UR\Service\DTO\DataImportTable\ColumnIndex;

class Synchronizer
{
    const DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE = '__data_import_%d'; // %d is data set id
    const HIDDEN_FIELD_MONTH_TEMPLATE = '__%s_month';
    const HIDDEN_FIELD_YEAR_TEMPLATE = '__%s_year';
    const HIDDEN_FIELD_DAY_TEMPLATE = '__%s_day';

    const FIELD_LENGTH_LARGE_TEXT = 65535;
    const FIELD_LENGTH_TEXT = 512;

    // TODO: organize const to right place. Current constants are discrete
    const FIELD_LENGTH_COLUMN_UNIQUE_ID = 32; // current use md5(dimension1:dimension2:...) so that length is 32

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
        $dataSetImportTable->addColumn(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, Type::INTEGER, array('unsigned' => true, 'notnull' => true));
        $dataSetImportTable->addColumn(DataSetInterface::IMPORT_ID_COLUMN, Type::INTEGER, array('unsigned' => true, 'notnull' => true));
        $dataSetImportTable->addColumn(DataSetInterface::UNIQUE_ID_COLUMN, Type::STRING, array('notnull' => true, "length" => Synchronizer::FIELD_LENGTH_COLUMN_UNIQUE_ID, "fixed" => true)); // CHAR instead of VARCHAR
        $dataSetImportTable->addColumn(DataSetInterface::OVERWRITE_DATE, FieldType::DATETIME, array('notnull' => false, 'default' => null));

        // add dimensions
        foreach ($dataSet->getDimensions() as $fieldName => $fieldType) {
            if ($fieldType === FieldType::NUMBER) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::DECIMAL) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::LARGE_TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_LARGE_TEXT]);
            } else if ($fieldType === FieldType::TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_TEXT]);
            } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);

                $colTypeDayOrMonthOrYear = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[FieldType::NUMBER];
                $dataSetImportTable->addColumn(Synchronizer::getHiddenColumnDay($fieldName), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $dataSetImportTable->addColumn(Synchronizer::getHiddenColumnMonth($fieldName), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $dataSetImportTable->addColumn(Synchronizer::getHiddenColumnYear($fieldName), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
            } else {
                $dataSetImportTable->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);
            }
        }

        // add metrics
        foreach ($dataSet->getMetrics() as $fieldName => $fieldType) {
            if ($fieldType === FieldType::NUMBER) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::DECIMAL) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::LARGE_TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_LARGE_TEXT]);
            } else if ($fieldType === FieldType::TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => self::FIELD_LENGTH_TEXT]);
            } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetImportTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);

                // add month and year also
                $colTypeDayOrMonthOrYear = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[FieldType::NUMBER];
                $dataSetImportTable->addColumn(self::getHiddenColumnDay($fieldName), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $dataSetImportTable->addColumn(self::getHiddenColumnMonth($fieldName), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $dataSetImportTable->addColumn(self::getHiddenColumnYear($fieldName), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
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
            throw new \mysqli_sql_exception(sprintf('Cannot Sync Schema %s, exception: %s', $schema->getName(), $e->getMessage()));
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
         *     // multiple columns if need create index for multiple columns
         *     [ columnIndex1, columnIndex2, ... ],
         *     ...
         * ];
         */
        $columnIndexes = [
            [
                // index on single multiple column
                new ColumnIndex(DataSetInterface::DATA_SOURCE_ID_COLUMN, FieldType::NUMBER)
            ],
            [
                // index on single multiple column
                new ColumnIndex(DataSetInterface::IMPORT_ID_COLUMN, FieldType::NUMBER)
            ],
            [
                // index on single multiple column
                new ColumnIndex(DataSetInterface::OVERWRITE_DATE, FieldType::NUMBER)
            ],
            [
                // index on multiple columns
                new ColumnIndex(DataSetInterface::UNIQUE_ID_COLUMN, FieldType::TEXT, Synchronizer::FIELD_LENGTH_COLUMN_UNIQUE_ID), // special for __unique_id
                new ColumnIndex(DataSetInterface::OVERWRITE_DATE, FieldType::NUMBER)
            ]
        ];

        // add dimensions, also add indexes for all dimensions
        foreach ($dataSet->getDimensions() as $fieldName => $fieldType) {
            // add index for column
            $columnIndexes[] = [
                new ColumnIndex($fieldName, $fieldType)
            ];

            // add indexes for hidden columns day/month/year if this column type is date|datetime
            if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $hiddenDayColumn = self::getHiddenColumnDay($fieldName);
                $columnIndexes[] = [
                    new ColumnIndex($hiddenDayColumn, FieldType::NUMBER)
                ];

                $hiddenMonthColumn = self::getHiddenColumnMonth($fieldName);
                $columnIndexes[] = [
                    new ColumnIndex($hiddenMonthColumn, FieldType::NUMBER)
                ];

                $hiddenYearColumn = self::getHiddenColumnYear($fieldName);
                $columnIndexes[] = [
                    new ColumnIndex($hiddenYearColumn, FieldType::NUMBER)
                ];
            }
        }

        $createdIndexesCount = 0;

        // execute prepared statement for creating indexes
        $conn->beginTransaction();

        foreach ($columnIndexes as $multipleColumnIndexes) {
            /** @var ColumnIndex[] $multipleColumnIndexes */
            if (!is_array($multipleColumnIndexes)) {
                continue;
            }

            $columnNames = []; // for building index name
            $columnNamesAndLengths = []; // for building sql create index

            // build index for multiple columns
            foreach ($multipleColumnIndexes as $singleColumnIndex) {
                if (!$singleColumnIndex instanceof ColumnIndex) {
                    continue;
                }

                $columnName = $singleColumnIndex->getColumnName();
                if (!$dataSetImportTable->hasColumn($columnName)) {
                    continue; // column not found
                }

                $columnNames[] = $columnName;

                $columnLength = $singleColumnIndex->getColumnLength();
                $columnNamesAndLengths[] = (null === $columnLength)
                    ? $columnName
                    : sprintf('%s(%s)', $columnName, $columnLength);
            }

            // sure have columns to be created index
            if (empty($columnNames) || empty($columnNamesAndLengths)) {
                continue;
            }

            $indexName = self::getDataSetImportTableIndexName($dataSetImportTable->getName(), $columnNames);

            // update inUsedIndexes
            $inUsedIndexes[] = $indexName;

            if ($dataSetImportTable->hasIndex($indexName)) {
                continue; // already has index
            }

            $createdIndexesCount++;

            self::prepareStatementCreateIndex($conn, $indexName, $dataSetImportTable->getName(), $columnNamesAndLengths);
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
     * @param array|string[] $columnNames
     * @return string
     */
    public static function getDataSetImportTableIndexName($dataImportTableName, array $columnNames)
    {
        $concatenatedColumnNames = implode('_', $columnNames);

        return sprintf(self::DATA_IMPORT_TABLE_INDEX_PREFIX_TEMPLATE, $dataImportTableName, $concatenatedColumnNames);
    }

    /**
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
            ? Synchronizer::FIELD_LENGTH_LARGE_TEXT
            : ($columnType === FieldType::TEXT
                ? Synchronizer::FIELD_LENGTH_TEXT
                : null // other types: not set length
            );

        // hot fix for UNIQUE_ID
        // TODO: refactor to use one definition place
        if ($columnName === DataSetInterface::UNIQUE_ID_COLUMN) {
            $columnLength = Synchronizer::FIELD_LENGTH_COLUMN_UNIQUE_ID;
        }

        self::prepareStatementAlterColumnType($conn, $columnName, $dataTable->getName(), $columnType, $columnLength);

        return true;
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
     * @param array $columnNamesAndLengths
     * @throws DBALException
     */
    public static function prepareStatementCreateIndex(Connection $conn, $indexName, $tableName, array $columnNamesAndLengths)
    {
        $updateSql = self::createIndexSql($indexName, $tableName, $columnNamesAndLengths);
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
     * createIndexSql
     * e.g: CREATE INDEX __data_import_1_field1_field2 ON __data_import_1 (field1(512),field2)
     *
     * @param $indexName
     * @param $tableName
     * @param array $columnNamesAndLengths
     * @return string sql create index
     */
    public static function createIndexSql($indexName, $tableName, array $columnNamesAndLengths)
    {
        $concatenatedColumnNamesAndLengths = implode(',', $columnNamesAndLengths);

        return sprintf('CREATE INDEX %s ON %s (%s)',
            $indexName,
            $tableName,
            $concatenatedColumnNamesAndLengths
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

    /**
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
     * @param $tableName
     * @param $addColumn
     * @param $type
     * @param $option
     * @throws DBALException
     */
    public function createColumnNumOfPendingLoad($tableName, $addColumn, $type, $option)
    {
        $dataSetTable = $this->getTable($tableName);

        if ($dataSetTable instanceof Table) {
            if (!$dataSetTable->hasColumn($addColumn)) {
                $addColumn = new Column($addColumn, $type, $option);
                $tableDiff = new TableDiff($tableName, [$addColumn]);
                $addColumnSqls = $this->conn->getDatabasePlatform()->getAlterTableSQL($tableDiff);
                try {
                    foreach ($addColumnSqls as $addColumnsSql) {
                        $this->conn->exec($addColumnsSql);
                    }
                } catch (\Exception $e) {

                }
            }
        }
    }

    /**
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