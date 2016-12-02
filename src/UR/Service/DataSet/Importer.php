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
    private static $restrictedColumns = ['__id', '__data_source_id', '__import_id'];

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
        array_push($columns, "__data_source_id", "__import_id");
        $rows = $collection->getRows();
//        $qb = $this->conn->createQueryBuilder();
        $this->conn->beginTransaction();
        try {
            $duplicateFields = $connectedDataSource->getDuplicates();

            foreach ($rows as $row) {
                $duplicateSql = 'SELECT * FROM ' . $tableName;
                if (count($duplicateFields)) {
                    $duplicateSql .= " WHERE ";
                    foreach ($duplicateFields as $duplicateField) {
                        $duplicateSql .= $duplicateField . "= :" . $duplicateField . ' AND ';
                    }
                    $duplicateSql = substr($duplicateSql, 0, -4);

                    $dupQb = $this->conn->prepare($duplicateSql);

                    foreach ($duplicateFields as $duplicateField) {
                        $dupQb->bindValue($duplicateField, $row[$duplicateField]);
                    }
                } else {
                    $dupQb = $this->conn->prepare($duplicateSql);
                }

                $dupQb->execute();
                $isDup = $dupQb->fetchAll();

                if (count($duplicateFields) < 1 || count($isDup) < 1) {
                    $sql = 'INSERT INTO ' . $tableName . "( ";
                    $value = ' VALUES ' . "( ";
                    foreach ($columns as $column) {
                        $sql = $sql . $column . ', ';
                        $value = $value . ":" . $column . ', ';
                    }
                    $sql = substr($sql, 0, -2);
                    $sql .= ')';
                    $value = substr($value, 0, -2);
                    $value .= ')';
                    $sql .= $value;
                } else {
                    $sql = 'UPDATE ' . $tableName;
                    $value = ' SET ';
                    $where = ' WHERE __id= ' . $isDup[0]['__id'];
                    foreach ($columns as $column) {
                        $value .= $column . "= :" . $column . ', ';
                    }
                    $value = substr($value, 0, -2);
                    $sql .= $value . $where;
                }
                $qb = $this->conn->prepare($sql);
                $row['__data_source_id'] = $connectedDataSource->getDataSource()->getId();
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