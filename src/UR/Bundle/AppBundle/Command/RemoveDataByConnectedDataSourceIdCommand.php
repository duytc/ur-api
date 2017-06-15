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
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class RemoveDataByConnectedDataSourceIdCommand extends ContainerAwareCommand
{
    use StringUtilTrait;
    private $em;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:internal:data-import-table:remove-by-connected-data-source-id')
            ->addArgument('dataSetId', InputArgument::REQUIRED, 'Data Set Id')
            ->addArgument('connectedDataSourceId', InputArgument::REQUIRED, 'Connected Data Source Id')
            ->setDescription('Remove data from connected data source ');
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
        /** @var DataSetInterface $connectedDataSource */
        $dataSetId = $input->getArgument('dataSetId');
        $connectedDataSourceId = $input->getArgument('connectedDataSourceId');

        /* validate inputs */
        if (!$this->validateInput($dataSetId, $connectedDataSourceId, $output)) {
            throw new \Exception(sprintf('command run failed: params must integer'));
        }

        $this->em = $container->get('doctrine.orm.entity_manager');
        /**
         * @var Connection $conn
         */
        $conn = $this->em->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
        if (!$dataTable) {
            $logger->error(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            return 0;
        }

        try {
            $logger->notice(sprintf('deleting data from %s with Connected data source (ID:%s)', $dataTable->getName(), $connectedDataSourceId));
            $conn->beginTransaction();
            $this->deleteDataByConnectedDataSourceId($dataTable->getName(), $conn, $connectedDataSourceId);
            $conn->commit();
            $conn->close();
            $logger->notice('success delete data from data set');

        } catch (Exception $exception) {
            $logger->error(sprintf('cannot deleting data from connected data source (ID: %s) of %s cause: %s', $connectedDataSourceId, $dataTable->getName(), $exception->getMessage()));
        }
    }

    private function deleteDataByConnectedDataSourceId($tableName, Connection $connection, $connectedDataSourceId)
    {
        /*
         * DELETE FROM __data_import_3 where __connected_data_source_id = :__connected_data_source_id
         */

        $deleteSql = sprintf(" DELETE FROM %s WHERE  %s = :%s",
            $tableName,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN,
            DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN
        );

        $qb = $connection->prepare($deleteSql);
        $qb->bindValue(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, $connectedDataSourceId);
        $qb->execute();
    }

    protected function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @param $dataSetId
     * @param $connectedDataSourceId
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput($dataSetId, $connectedDataSourceId, OutputInterface $output)
    {
        function isInteger($input)
        {
            return (ctype_digit(strval($input)));
        }

        // validate input
        if (!isInteger($dataSetId) || !isInteger($connectedDataSourceId)) {
            $output->writeln(sprintf('command run failed: params must be an integer'));
            return false;
        }
        return true;
    }
}