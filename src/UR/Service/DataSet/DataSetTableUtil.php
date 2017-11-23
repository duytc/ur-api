<?php


namespace UR\Service\DataSet;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\DataSetInterface;

class DataSetTableUtil implements DataSetTableUtilInterface
{
    /** @var \Doctrine\DBAL\Connection  */
    private $connection;

    /** @var Synchronizer  */
    private $sync;

    /** @var EntityManagerInterface  */
    private $em;

    /**
     * DataSetTableUtil constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @inheritdoc
     */
    public function updateIndexes(DataSetInterface $dataSet) {
        $table = $this->getDataSetTable($dataSet);

        if (!$table instanceof Table) {
            return;
        }

        $this->getSync()->updateIndexes($this->getConnection(), $table, $dataSet);
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        if (!$this->connection instanceof Connection) {
            $this->connection = $this->em->getConnection();
        }

        return $this->connection;
    }

    /**
     * @return Synchronizer
     */
    public function getSync()
    {
        if (!$this->sync instanceof Synchronizer) {
            $this->sync = new Synchronizer($this->getConnection(), new Comparator());
        }

        return $this->sync;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return Table|false
     */
    private function getDataSetTable(DataSetInterface $dataSet) {
        return $this->getSync()->getDataSetImportTable($dataSet->getId());
    }
}