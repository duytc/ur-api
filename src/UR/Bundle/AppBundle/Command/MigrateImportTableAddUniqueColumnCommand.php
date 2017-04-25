<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSet\FieldType;
use UR\Service\StringUtilTrait;

class MigrateImportTableAddUniqueColumnCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-table:add-unique-column')
            ->setDescription('Add column __unique_id for data import table if not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /** @var IntegrationManagerInterface $integrationManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface[] $dataSets */
        $dataSets = $dataSetManager->all();

        $em = $container->get('doctrine.orm.entity_manager');
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

            if ($dataTable->hasColumn(DataSetInterface::UNIQUE_ID_COLUMN)) {
                continue;
            }

            $dataSetMissingUniques[] = $dataSet;
        }

        foreach ($dataSetMissingUniques as $dataSetMissingUnique) {
            $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSetMissingUnique->getId());
            $addCols = [];
            $addCols[] = $dataSetTable->addColumn(DataSetInterface::UNIQUE_ID_COLUMN, Type::STRING, array("notnull" => true, "length" => Synchronizer::FIELD_LENGTH_COLUMN_UNIQUE_ID));

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

            $dimensions = array_keys($dataSetMissingUnique->getDimensions());
            $qb = $conn->createQueryBuilder();

            foreach ($dimensions as $dimension) {
                $qb->addSelect($dimension);
                $qb->addSelect(DataSetInterface::ID_COLUMN);
            }

            $qb->from($dataSetTable->getName());
            $rows = $qb->execute()->fetchAll();

            foreach ($rows as $row) {
                $id = $row[DataSetInterface::ID_COLUMN];
                unset($row[DataSetInterface::ID_COLUMN]);

                foreach ($row as $k => $item) {
                    if ($item === null) {
                        unset($row[$k]);
                    }
                }

                $uniqueId = md5(implode(":", $row));

                $updateQb = $conn->createQueryBuilder();
                $updateQb->update($dataSetTable->getName(), 't')->set(DataSetInterface::UNIQUE_ID_COLUMN, "'" . $uniqueId . "'");
                $updateQb->where('t.__id=:table_id')->setParameter(':table_id', $id);
                $updateQb->execute();
            }
        }

        $this->logger->info(sprintf('command run successfully'));
    }
}