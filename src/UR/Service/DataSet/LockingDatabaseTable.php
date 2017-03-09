<?php

namespace UR\Service\DataSet;


use Doctrine\DBAL\Connection;
use UR\Exception\SqlLockTableException;

class LockingDatabaseTable
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * LockingDatabaseTable constructor.
     * @param Connection $conn
     */
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function lockTable($tableName)
    {
        $checkLockSql = sprintf("SHOW OPEN TABLES WHERE `Table` LIKE '%s' AND In_use > 0", $tableName);
        $stmt = $this->conn->executeQuery($checkLockSql);
        $isLocked = $stmt->fetchAll();
        if (count($isLocked) > 0) {
            throw new SqlLockTableException();
        }

        $lockSql = sprintf('LOCK TABLE %s WRITE', $tableName);
        $this->conn->exec($lockSql);
    }

    public function unLockTable()
    {
        $unlockSql = sprintf('UNLOCK TABLES');
        $this->conn->exec($unlockSql);
    }
}