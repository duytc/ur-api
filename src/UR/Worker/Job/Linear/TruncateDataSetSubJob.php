<?php

namespace UR\Worker\Job\Linear;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;

class TruncateDataSetSubJob implements SubJobInterface
{
    const JOB_NAME = 'truncateDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSetManagerInterface $dataSetManager
     */
    private $dataSetManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    private $conn;

    /**
     * @var ImportHistoryManagerInterface
     */
    protected $importHistoryManager;

    public function __construct(
        LoggerInterface $logger,
        DataSetManagerInterface $dataSetManager,
        EntityManagerInterface $entityManager,
        ImportHistoryManagerInterface $importHistoryManager
    )
    {
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->entityManager = $entityManager;
        $this->conn = $entityManager->getConnection();
        $this->importHistoryManager = $importHistoryManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);

        try {
            /**
             * @var DataSetInterface $dataSet
             */
            $dataSet = $this->dataSetManager->find($dataSetId);

            if (!$dataSet instanceof DataSetInterface) {
                throw new \Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

            // check if table not existed
            if (!$dataTable instanceof Table) {
                throw new \Exception(sprintf('table with data set id :  dose not exist', $dataSetId));
            }

            $truncateSQL = sprintf("TRUNCATE %s", $dataTable->getName());
            $this->conn->exec($truncateSQL);

            $this->importHistoryManager->deleteImportHistoryByDataSet($dataSet);

            $this->entityManager->persist($dataSet);
            $this->entityManager->flush();

            $this->logger->notice(sprintf('Truncate data set %s with table name %s', $dataSetId, $dataTable->getName()));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Could not truncate data from data set (ID: %s) error occur: %s', $dataSetId, $e->getMessage()));
        } finally {
            $this->entityManager->clear();
            gc_collect_cycles();
        }
    }
}