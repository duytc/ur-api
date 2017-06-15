<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class MigrateImportTableAddEntryDateColumnCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-table:add:entry-date-column')
            ->setDescription('Add column __entry_date for data import table if not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /** @var DataSetManagerInterface $dataSetManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface[] $dataSets */
        $dataSets = $dataSetManager->all();

        $em = $container->get('doctrine.orm.entity_manager');

        /**
         * @var Connection $conn
         */
        $conn = $em->getConnection();
        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        /** @var DataSetInterface[] $dataSetMissingUniques */
        $dataSetMissingUniques = [];
        foreach ($dataSets as $dataSet) {
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
            if (!$dataTable) {
                continue;
            }

            if ($dataTable->hasColumn(DataSetInterface::ENTRY_DATE_COLUMN)) {
                continue;
            }

            $dataSetMissingUniques[] = $dataSet;
        }

        foreach ($dataSetMissingUniques as $dataSetMissingUnique) {
            $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSetMissingUnique->getId());
            $addCols = [];
            $addCols[] = $dataSetTable->addColumn(DataSetInterface::ENTRY_DATE_COLUMN, Type::DATETIME, array("notnull" => true));

            $updateTable = new TableDiff($dataSetTable->getName(), $addCols, [], [], [], [], []);

            try {
                $dataSetSynchronizer->syncSchema($schema);
                $alterSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
                foreach ($alterSqls as $alterSql) {
                    $conn->exec($alterSql);
                }
            } catch (\Exception $e) {
                $logger->error("Cannot Sync Schema " . $schema->getName());
            }
        }

        $this->logger->info(sprintf('command run successfully'));
    }
}