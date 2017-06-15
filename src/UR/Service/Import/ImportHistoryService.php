<?php

namespace UR\Service\Import;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\DataSet\Synchronizer;

class ImportHistoryService
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
    }

    /**
     * @param array $importHistoryIds
     * @param $dataSetId
     */
    public function deleteImportedDataByImportHistories(array $importHistoryIds, $dataSetId)
    {
        if (count($importHistoryIds) < 1) {
            return;
        }

        $conn = $this->em->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
        if (!$dataTable) {
            return;
        }

        $tableName = $dataTable->getName();

        $query = sprintf('DELETE FROM %s WHERE %s IN (%s)', $tableName, DataSetInterface::IMPORT_ID_COLUMN, implode(',', $importHistoryIds));
        $stmt = $conn->prepare($query);
        $stmt->execute();

        $this->em->flush();
        $conn->close();
    }

    public function deleteImportedDataWithOutDataSet($importHistories)
    {
        $conn = $this->em->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        /**@var ImportHistoryInterface $importHistory */
        foreach ($importHistories as $importHistory) {
            if (!$importHistory instanceof ImportHistoryInterface) {
                continue;
            }

            $dataSetId = $importHistory->getDataSet()->getId();
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
            if (!$dataTable) {
                return;
            }

            $tableName = $dataTable->getName();
            $query = sprintf('DELETE FROM %s WHERE %s = ?', $tableName, DataSetInterface::IMPORT_ID_COLUMN);
            $stmt = $conn->prepare($query);
            $stmt->bindValue(1, $importHistory->getId());
            $stmt->execute();
            $this->em->remove($importHistory);

            //TODO UPDATE TOTAL ROW
//            $this->updateDataSetTotalRow($importHistory->getDataSet()->getId());
        }

        $this->em->flush();
        $conn->close();
    }
}