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

    private static $restrictedColumns = ['__id', '__date_source_id', '__import_id'];

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function importCollection(Collection $collection, Table $table)
    {
        $tableName = $table->getName();
        $tableColumns = array_keys($table->getColumns());

        $columns = array_intersect($collection->getColumns(), $tableColumns);
        $columns = array_values($columns);

        foreach($columns as $column) {
            if (in_array($column, self::$restrictedColumns, true)) {
                throw new \InvalidArgumentException(sprintf('%s cannot be used as a column name. It is reserved for internal use.', $column));
            }

            if (!preg_match('#[_a-z]+#i', $column)) {
                throw new \InvalidArgumentException(sprintf('column names can only contain alpha characters and underscores'));
            }
        }

        $rows = $collection->getRows();

        $qb = $this->conn->createQueryBuilder();

        $this->conn->beginTransaction();

        try {
            foreach ($rows as $row) {
                $query = $qb
                    ->insert($tableName)
                ;

                $positionKey = 0;
                
                foreach($columns as $column) {
                    $query->setValue($column, '?');
                    // todo bind param type
                    $query->setParameter($positionKey, $row[$column]);

                    $positionKey++;
                }

                $query->execute();

                unset($query);
            }

            $this->conn->commit();
        } catch(\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return true;
    }
}