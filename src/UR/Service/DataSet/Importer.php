<?php
namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;
use \Doctrine\DBAL\Schema\Table;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class Importer
{
    /**
     * @var Connection
     */
    protected $conn;
    protected $batchSize = 500;
    private static $restrictedColumns = ['__id', '__data_source_id', '__import_id'];
    protected $preparedUpdateCount = 0;
    protected $preparedInsertCount = 0;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function importCollection(Collection $collection, Table $table, $importId, ConnectedDataSourceInterface $connectedDataSource)
    {
        $tableName = $table->getName();
        $tableColumns = array_keys($table->getColumns());
        $columns = array_intersect($collection->getColumns(), $tableColumns);
        $columns = array_values($columns);
        foreach ($columns as $column) {
            if (in_array($column, self::$restrictedColumns, true)) {
                throw new \InvalidArgumentException(sprintf('%s cannot be used as a column name. It is reserved for internal use.', $column));
            }
            if (!preg_match('#[_a-z]+#i', $column)) {
                throw new \InvalidArgumentException(sprintf('column names can only contain alpha characters and underscores'));
            }
        }

        $rows = array_values($collection->getRows());
        if (!is_array($rows) || count($rows) < 1) {
            return true;
        }

        $columns = array_keys($rows[0]);
        array_push($columns, "__data_source_id", "__import_id");

        $duplicateFields = $connectedDataSource->getDuplicates();

        $preparedCounts = 0;

        $insert_values = array();
        foreach ($rows as $row) {

            //check duplicate
            $isDup = [];
            if (count($duplicateFields) > 0) {
                $duplicateSql = 'SELECT * FROM ' . $tableName;
                $duplicateSql .= " WHERE ";
                foreach ($duplicateFields as $duplicateField) {
                    $duplicateSql .= $duplicateField . "= :" . $duplicateField . ' AND ';
                }

                $duplicateSql = substr($duplicateSql, 0, -4);
                $dupQb = $this->conn->prepare($duplicateSql);

                foreach ($duplicateFields as $duplicateField) {
                    $dupQb->bindValue($duplicateField, $row[$duplicateField]);
                }

                $dupQb->execute();
                $isDup = $dupQb->fetchAll();
            }

            $row['__data_source_id'] = $connectedDataSource->getDataSource()->getId();
            $row['__import_id'] = $importId;

            //insert
            if (count($duplicateFields) < 1 || count($isDup) < 1) {
                $question_marks[] = '(' . $this->placeholders('?', sizeof($row)) . ')';
                $insert_values = array_merge($insert_values, array_values($row));
                $preparedCounts++;
                if ($preparedCounts === $this->batchSize) {
                    $preparedCounts = 0;
                    $insertSql = "INSERT INTO " . $tableName . "(" . implode(",", $columns) . ") VALUES " . implode(',', $question_marks);
                    $this->executeInsert($insertSql, $insert_values);
                    $insert_values = [];
                    $question_marks = [];
                }
            } else { //update
                $updateSql = 'UPDATE ' . $tableName;
                $value = ' SET ';
                $where = ' WHERE __id= ' . $isDup[0]['__id'];
                foreach ($columns as $column) {
                    $value .= $column . "= :" . $column . ', ';
                }

                $value = substr($value, 0, -2);
                $updateSql .= $value . $where;
                if ($this->preparedUpdateCount === 0) {
                    $this->conn->beginTransaction();
                }

                $qb = $this->conn->prepare($updateSql);
                foreach ($columns as $column) {
                    $rowValue = strcmp($row[$column], "") === 0 ? null : $row[$column];
                    $qb->bindValue($column, $rowValue);
                }
                $this->preparedUpdateCount++;

                try {
                    $qb->execute($updateSql);
                } catch (\Exception $e) {
                    $this->conn->rollBack();
                    throw $e;
                }

                if ($this->preparedUpdateCount === $this->batchSize) {
                    $this->preparedUpdateCount = 0;
                    $this->conn->commit();
                }
            }

        }

        if ($preparedCounts > 0) {
            $insertSql = "INSERT INTO " . $tableName . "(" . implode(",", $columns) . ") VALUES " . implode(',', $question_marks);
            $this->executeInsert($insertSql, $insert_values);
        }

        if ($this->preparedUpdateCount > 0) {
            $this->conn->commit();
        }

        return true;
    }

    function placeholders($text, $count = 0, $separator = ",")
    {
        $result = array();
        if ($count > 0) {
            for ($x = 0; $x < $count; $x++) {
                $result[] = $text;
            }
        }

        return implode($separator, $result);
    }

    function executeInsert($sql, array $values)
    {
        if ($this->preparedUpdateCount > 0) {
            $this->conn->commit(); //commit updates fields
            $this->preparedUpdateCount = 0;
        }

        $this->conn->beginTransaction();
        $stmt = $this->conn->prepare($sql);
        try {
            $stmt->execute($values);
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
        $this->conn->commit();
        $this->conn->close();
    }
}