<?php

namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;

class Locator
{
    const PREFIX_DATA_IMPORT_TABLE = '__data_import_%d';
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
        return sprintf(self::PREFIX_DATA_IMPORT_TABLE, $id);
    }
}