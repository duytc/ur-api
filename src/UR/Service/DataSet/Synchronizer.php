<?php

namespace UR\Service\DataSet;

use \Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;

class Synchronizer
{
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

        foreach($saveQueries as $sql) {
            $this->conn->exec($sql);
        }

        return $this;
    }
}