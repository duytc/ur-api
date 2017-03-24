<?php

namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use UR\Model\Core\DataSetInterface;

class Synchronizer
{
    const PREFIX_DATA_IMPORT_TABLE = '__data_import_%d';
    const MONTH_FIELD_TEMPLATE = '__%s_month';
    const YEAR_FIELD_TEMPLATE = '__%s_year';
    const DAY_FIELD_TEMPLATE = '__%s_day';


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
     * @throws \Doctrine\DBAL\DBALException
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
        $table = $this->getDataSetImportTable($dataSet->getId());

        if ($table instanceof Table) {
            return $table;
        }

        $schema = new Schema();

        $dataSetTable = $schema->createTable($this->getDataSetImportTableName($dataSet->getId()));
        $dataSetTable->addColumn(DataSetInterface::ID_COLUMN, Type::INTEGER, array("autoincrement" => true, "unsigned" => true));
        $dataSetTable->setPrimaryKey(array(DataSetInterface::ID_COLUMN));
        $dataSetTable->addColumn(DataSetInterface::DATA_SOURCE_ID_COLUMN, Type::INTEGER, array("unsigned" => true, "notnull" => true));
        $dataSetTable->addColumn(DataSetInterface::IMPORT_ID_COLUMN, Type::INTEGER, array("unsigned" => true, "notnull" => true));
        $dataSetTable->addColumn(DataSetInterface::UNIQUE_ID_COLUMN, FieldType::TEXT, array("notnull" => true));
        $dataSetTable->addColumn(DataSetInterface::OVERWRITE_DATE, FieldType::DATETIME, array("notnull" => false, "default" => null));

        // create import table
        // add dimensions
        foreach ($dataSet->getDimensions() as $key => $value) {
            $dataSetTable->addColumn($key, $value, ["notnull" => false, "default" => null]);
            if (strcmp($value, FieldType::DATE) === 0) {
                // add month and year also
                $dataSetTable->addColumn(sprintf(self::DAY_FIELD_TEMPLATE, $key), Type::INTEGER, ["notnull" => false, "default" => null]);
                $dataSetTable->addColumn(sprintf(self::MONTH_FIELD_TEMPLATE, $key), Type::INTEGER, ["notnull" => false, "default" => null]);
                $dataSetTable->addColumn(sprintf(self::YEAR_FIELD_TEMPLATE, $key), Type::INTEGER, ["notnull" => false, "default" => null]);
            }
        }

        // add metrics
        foreach ($dataSet->getMetrics() as $key => $value) {
            if (strcmp($value, FieldType::NUMBER) === 0) {
                $dataSetTable->addColumn($key, "integer", ["notnull" => false, "default" => null]);
            } else if (strcmp($value, FieldType::DECIMAL) === 0) {
                $dataSetTable->addColumn($key, $value, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($value, FieldType::MULTI_LINE_TEXT) === 0) {
                $dataSetTable->addColumn($key, FieldType::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($value, FieldType::DATE) === 0) {
                $dataSetTable->addColumn($key, FieldType::DATE, ["notnull" => false, "default" => null]);
                // add month and year also
                $dataSetTable->addColumn(sprintf(self::DAY_FIELD_TEMPLATE, $key), Type::INTEGER, ["notnull" => false, "default" => null]);
                $dataSetTable->addColumn(sprintf(self::MONTH_FIELD_TEMPLATE, $key), Type::INTEGER, ["notnull" => false, "default" => null]);
                $dataSetTable->addColumn(sprintf(self::YEAR_FIELD_TEMPLATE, $key), Type::INTEGER, ["notnull" => false, "default" => null]);
            } else {
                $dataSetTable->addColumn($key, $value, ["notnull" => false, "default" => null]);
            }
        }

        //// create table
        try {
            $this->syncSchema($schema);

            $truncateSql = $this->conn->getDatabasePlatform()->getTruncateTableSQL($this->getDataSetImportTableName($dataSet->getId()));

            $this->conn->exec($truncateSql);
        } catch (\Exception $e) {
            throw new \mysqli_sql_exception("Cannot Sync Schema " . $schema->getName());
        }

        return $dataSetTable;
    }

    /**
     * @param int $id
     * @return \Doctrine\DBAL\Schema\Table|false
     */
    public function getDataSetImportTable($id)
    {
        $sm = $this->conn->getSchemaManager();

        $tableName = $this->getDataSetImportTableName($id);

        if (!$sm->tablesExist([$tableName])) {
            return false;
        }

        return $sm->listTableDetails($tableName);
    }

    public function getDataSetImportTableName($id)
    {
        return sprintf(self::PREFIX_DATA_IMPORT_TABLE, $id);
    }

    /**
     * @return Connection
     */
    public function getConn(): Connection
    {
        return $this->conn;
    }
}