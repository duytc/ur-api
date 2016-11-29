<?php

namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;
use \Doctrine\DBAL\Schema\Table;
use UR\Service\DTO\Collection;

class Importer
{
    /**
     * @var Connection
     */
    protected $conn;

    private static $restrictedColumns = ['__id', '__data_source_id', '__import_id'];

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function importCollection(Collection $collection, Table $table, $importId, $dataSourceId)
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

        array_push($columns, "__data_source_id", "__import_id");

        $rows = $collection->getRows();
//        $qb = $this->conn->createQueryBuilder();
        $this->conn->beginTransaction();
        try {

            foreach ($rows as $row) {
                $sql = 'INSERT INTO ' . $tableName . "( ";
                $value = ' VALUES ' . "( ";
                $onDuplicate = ' ON DUPLICATE KEY UPDATE ';
                foreach ($columns as $column) {
                    $sql = $sql . $column . ', ';
                    $value = $value . ":" . $column . ', ';
                    $onDuplicate .= $column . "= :" . $column . ', ';
                }
                $sql .= ')';
                $sql = str_replace(", )", ")", $sql);
                $value .= ')';
                $value = str_replace(", )", ")", $value);
                $onDuplicate .= ')';
                $onDuplicate = str_replace(", )", "", $onDuplicate);

                $sql .= $value . $onDuplicate;
                $qb = $this->conn->prepare($sql);
                $row['__data_source_id'] = $dataSourceId;
                $row['__import_id'] = $importId;
                foreach ($columns as $column) {
                    $rowValue = strcmp($row[$column], "") === 0 ? null : $row[$column];
                    $qb->bindValue($column, $rowValue);
                }
                $qb->execute();
                unset($qb);
            }

            $this->conn->commit();
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return true;
    }
}