<?php
namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;
use \Doctrine\DBAL\Schema\Table;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;

class Importer
{
    /**
     * @var Connection
     */
    const ID_COLUMN = '__id';
    const DATA_SOURCE_ID_COLUMN = '__data_source_id';
    const IMPORT_ID_COLUMN = '__import_id';

    protected $conn;
    protected $batchSize;
    private static $restrictedColumns = [self::ID_COLUMN, self::DATA_SOURCE_ID_COLUMN, self::IMPORT_ID_COLUMN, DataSetInterface::UNIQUE_ID_COLUMN];
    protected $preparedUpdateCount;
    protected $preparedInsertCount;

    public function __construct(Connection $conn, $batchSize)
    {
        $this->conn = $conn;
        $this->batchSize = $batchSize;
    }

    public function importCollection(Collection $collection, Table $table, $importId, ConnectedDataSourceInterface $connectedDataSource)
    {
        $tableName = $table->getName();
        $dimensions = $connectedDataSource->getDataSet()->getDimensions();
        $metrics = $connectedDataSource->getDataSet()->getMetrics();
        $allFields = array_merge($dimensions, $metrics);
        $collection = $this->getRawData($collection, $allFields, $connectedDataSource);
        $rows = $collection->getRows();
        $columns = $collection->getColumns();

        $dimensionMapping = [];
        $metricMappings = [];

        foreach ($columns as $k => $column) {
            if (in_array($column, self::$restrictedColumns, true)) {
                throw new \InvalidArgumentException(sprintf('%s cannot be used as a column name. It is reserved for internal use.', $column));
            }
            if (!preg_match('#[_a-z]+#i', $column)) {
                throw new \InvalidArgumentException(sprintf('column names can only contain alpha characters and underscores'));
            }

            if (array_key_exists($column, $dimensions)) {
                $dimensionMapping[$k] = $column;
            }

            if (array_key_exists($column, $metrics)) {
                $metricMappings[$k] = $column;
            }
        }

        if (!is_array($rows) || count($rows) < 1) {
            return true;
        }

        $isOverwriteData = $connectedDataSource->getDataSet()->getAllowOverwriteExistingData();
        $preparedCounts = 0;
        $insert_values = array();
        $columns[self::DATA_SOURCE_ID_COLUMN] = self::DATA_SOURCE_ID_COLUMN;
        $columns[self::IMPORT_ID_COLUMN] = self::IMPORT_ID_COLUMN;
        $columns[DataSetInterface::UNIQUE_ID_COLUMN] = DataSetInterface::UNIQUE_ID_COLUMN;
        $question_marks = [];
        $this->preparedUpdateCount = 0;
        $this->preparedInsertCount = 0;
        foreach ($rows as $row) {
            $uniqueKeys = array_intersect_key($row, $dimensionMapping);
            $uniqueId = md5(implode(":", $uniqueKeys));
            $row = array_intersect_key($row, $columns);
            $row[self::DATA_SOURCE_ID_COLUMN] = $connectedDataSource->getDataSource()->getId();
            $row[self::IMPORT_ID_COLUMN] = $importId;
            $row[DataSetInterface::UNIQUE_ID_COLUMN] = $uniqueId;
            //insert
            if (!$isOverwriteData) {
                $question_marks[] = '(' . $this->placeholders('?', sizeof($row)) . ')';
                $insert_values = array_merge($insert_values, array_values($row));
                $preparedCounts++;
                if ($preparedCounts === $this->batchSize) {
                    $preparedCounts = 0;
                    $insertSql = sprintf("INSERT INTO %s (%s) VALUES %s", $tableName, implode(",", $columns), implode(',', $question_marks));
                    $this->executeInsert($insertSql, $insert_values);
                    $insert_values = [];
                    $question_marks = [];
                }
            } else { //update
                $value = '';
                foreach ($metricMappings as $column) {
                    $value .= $column . "= :" . $column . ', ';
                }

                $value .= self::IMPORT_ID_COLUMN . "= :" . self::IMPORT_ID_COLUMN;
                $onDupSql = sprintf("INSERT INTO %s (%s) VALUES (:%s) ON DUPLICATE KEY UPDATE %s", $tableName, implode(",", $columns), implode(', :', $columns), $value);

                if ($this->preparedUpdateCount === 0) {
                    $this->conn->beginTransaction();
                }

                $qb = $this->conn->prepare($onDupSql);
                foreach ($columns as $k => $metricMapping) {
                    $rowValue = strcmp($row[$k], "") === 0 ? null : $row[$k];
                    $qb->bindValue($metricMapping, $rowValue);
                }

                $this->preparedUpdateCount++;
                try {
                    $qb->execute();
                } catch (\Exception $e) {
                    $this->conn->rollBack();
                    throw new ImportDataException(null, null, null);
                }

                if ($this->preparedUpdateCount === $this->batchSize) {
                    $this->preparedUpdateCount = 0;
                    $this->conn->commit();
                }
            }
        }

        if ($preparedCounts > 0 && is_array($columns) && is_array($question_marks)) {
            $insertSql = "INSERT INTO " . $tableName . "(" . implode(",", $columns) . ") VALUES " . implode(',', $question_marks);
            $this->executeInsert($insertSql, $insert_values);
        }

        if ($this->preparedUpdateCount > 0) {
            $this->conn->commit();
        }

        return true;
    }

    private function placeholders($text, $count = 0, $separator = ",")
    {
        $result = array();
        if ($count > 0) {
            for ($x = 0; $x < $count; $x++) {
                $result[] = $text;
            }
        }

        return implode($separator, $result);
    }

    private function executeInsert($sql, array $values)
    {
        if ($this->preparedUpdateCount > 0) {
            $this->conn->commit(); //commit updates fields
            $this->preparedUpdateCount = 0;
        }

        $this->conn->beginTransaction();
        $stmt = $this->conn->prepare($sql);
        try {
            $stmt->execute($values);
        } catch (\Exception $ex) {
            $this->conn->rollBack();
            throw new ImportDataException(null, null, null);
        }
        $this->conn->commit();
        $this->conn->close();
    }

    public function getRawData(Collection $collection, $allFields, ConnectedDataSourceInterface $connectedDataSource)
    {
        $tableColumns = array_keys($allFields);
        $rows = array_values($collection->getRows());
        $mapFields = $connectedDataSource->getMapFields();
        $columns = [];
        if (count($rows) < 1) {
            return $collection;
        }

        foreach ($rows[0] as $field => $value) {
            if (array_key_exists($field, $mapFields)) {
                if (in_array($mapFields[$field], $tableColumns)) {
                    $columns[$field] = $mapFields[$field];
                }
            }

            if (in_array($field, $collection->getColumns())) {
                if (in_array($field, $tableColumns))
                    $columns[$field] = $field;
            }
        }

        $columns = array_unique($columns);
        $collection->setRows($rows);
        $collection->setColumns($columns);

        return $collection;
    }
}