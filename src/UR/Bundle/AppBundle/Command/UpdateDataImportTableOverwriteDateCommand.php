<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class UpdateDataImportTableOverwriteDateCommand extends ContainerAwareCommand
{
    use StringUtilTrait;
    private $em;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:update:data-import-table:overwrite')
            ->addArgument('dataSetId', InputArgument::REQUIRED, 'Data set id')
            ->setDescription('update overwrite date for data import table');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        /**
         * @var Logger $logger
         */
        $logger = $container->get('logger');

        /* get inputs */
        /** @var DataSetManagerInterface $dataSetManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface $dataSet */
        $dataSetId = $input->getArgument('dataSetId');

        /* validate inputs */
        if (!$this->validateInput($dataSetId, $output)) {
            throw new \Exception(sprintf('command run failed: params must integer'));
        }

        $dataSet = $dataSetManager->find($dataSetId);
        $this->em = $container->get('doctrine.orm.entity_manager');
        /**
         * @var Connection $conn
         */
        $conn = $this->em->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        if (!$dataTable) {
            $logger->error(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            return 0;
        }

        try {
            if ($dataSet->getAllowOverwriteExistingData()) {
                $logger->notice('begin updating __overwrite_date for data set');
                $conn->beginTransaction();
                $this->setOverwriteDateToDataImportTable($dataTable->getName(), $conn);
                $conn->commit();
                $logger->notice('success updating __overwrite_date for data set');
            }

            $dataSetManager->save($dataSet);
        } catch (Exception $exception) {
            $logger->error(sprintf('cannot update __overwrite_date to %s cause: %s', $dataTable->getName(), $exception->getMessage()));
        }
        /*
         * close connection to delete temp table
         */
        $conn->close();

        return 0;
    }

    private function setOverwriteDateToDataImportTable($tableName, Connection $connection)
    {
        /*
         * CREATE TEMPORARY TABLE concurrency_example_overwrite (INDEX idx_1 (__unique_id, __entry_date, __import_id))
         * SELECT
            __unique_id,
            __entry_date,
            max(__import_id)  __import_id
            FROM (
                SELECT
                __unique_id,
                max(__entry_date) __entry_date,
                 __import_id
                FROM
                    (
                        SELECT
                        __unique_id,
                        __entry_date,
                        __import_id,
                        __connected_data_source_id
                    FROM __data_import_21
                    ORDER BY __entry_date DESC, __import_id DESC
                        LIMIT 18446744073709551615
                       ) rs1 GROUP BY __unique_id ORDER BY __import_id DESC
                LIMIT 18446744073709551615
               )rs2 group by __unique_id
         * UPDATE __data_import_3 set __overwrite_date = :__overwrite_date where __overwrite_date IS null AND (__unique_id, __entry_date, __import_id) NOT IN(SELECT * FROM concurrency_example_overwrite)
         */
        $index = sprintf("INDEX idx_1 (%s, %s, %s)", DataSetInterface::UNIQUE_ID_COLUMN, DataSetInterface::ENTRY_DATE_COLUMN, DataSetInterface::IMPORT_ID_COLUMN);

        $rs1Select = sprintf('SELECT %s, %s, %s, %s',
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN
        );

        $rs1From = sprintf('FROM %s ORDER BY %s DESC, %s DESC LIMIT 18446744073709551615',
            $tableName,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $rs1Query = sprintf('(%s %s) as rs1', $rs1Select, $rs1From);

        $rs2Select = sprintf('SELECT %s, MAX(%s) %s, %s',
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $rs2From = sprintf('FROM %s GROUP BY %s ORDER BY %s DESC LIMIT 18446744073709551615',
            $rs1Query,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $rs2Query = sprintf('(%s %s) as rs2', $rs2Select, $rs2From);

        $select = sprintf('SELECT %s, %s, max(%s) %s FROM %s GROUP BY %s',
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            $rs2Query,
            DataSetInterface::UNIQUE_ID_COLUMN
        );

        $createTempTableSql = sprintf("CREATE TEMPORARY TABLE concurrency_example_overwrite (%s) %s;", $index, $select);
//        $createTempTableSql2 = sprintf("CREATE TEMPORARY TABLE concurrency_example_overwrite (%s) %s;", $index, $query);

        $updateWhere = sprintf("%s IS null AND (%s, %s, %s) NOT IN (SELECT %s, %s, %s FROM concurrency_example_overwrite)",
            DataSetInterface::OVERWRITE_DATE,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN,
            DataSetInterface::UNIQUE_ID_COLUMN,
            DataSetInterface::ENTRY_DATE_COLUMN,
            DataSetInterface::IMPORT_ID_COLUMN
        );

        $updateSql = sprintf(" UPDATE %s set %s = now() where %s",
            $tableName,
            DataSetInterface::OVERWRITE_DATE,
            $updateWhere
        );

        $updateOverwriteSql = sprintf("%s %s", $createTempTableSql, $updateSql);
        $qb = $connection->prepare($updateOverwriteSql);
        $qb->bindValue(DataSetInterface::OVERWRITE_DATE, date('Y-m-d H:i:sP'));
        $qb->execute();
    }

    protected function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @param $dataSetId
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput($dataSetId, OutputInterface $output)
    {
        function isInteger($input)
        {
            return (ctype_digit(strval($input)));
        }

        // validate input
        if (!isInteger($dataSetId)) {
            $output->writeln(sprintf('command run failed: params must be an integer'));
            return false;
        }
        return true;
    }
}