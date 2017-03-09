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
     * @return Connection
     */
    public function getConn(): Connection
    {
        return $this->conn;
    }
}