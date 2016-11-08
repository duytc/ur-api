<?php

namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;

class Locator
{
    /**
     * @var Connection
     */
    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
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
        return sprintf('__data_import_%d', $id);
    }
}