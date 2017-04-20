<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class MigrateImportTableAddIndexesForDimensionsCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-table:add-indexes')
            ->setDescription('Add indexes for all dimensions of data import table if not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $output->writeln('Starting add indexes for all dimensions of data import table if not exist...');

        /** @var IntegrationManagerInterface $integrationManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface[] $dataSets */
        $dataSets = $dataSetManager->all();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var Connection $conn */
        $conn = $em->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        // do sync for all data import tables related to data sets
        $updatedDataImportsCount = 0;

        /** @var DataSetInterface[] $dataSetMissingUniques */
        foreach ($dataSets as $dataSet) {
            $dataImportTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
            if (!$dataImportTable) {
                continue;
            }

            // sync data import table
            try {
                $removedIndexesCount = 0;

                // update indexes
                $createdIndexesCount = Synchronizer::updateIndexes($conn, $dataImportTable, $dataSet, $removedIndexesCount);

                // count updated data import tables
                if ($createdIndexesCount > 0 || $removedIndexesCount > 0) {
                    $updatedDataImportsCount++;
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf('Apply for data import table %s [#%d] got error %s. Command stopped.', $dataSet->getName(), $dataSet->getId(), $e->getMessage()));
                return;
            }
        }

        $output->writeln(sprintf('Command has been completed: %d data import table updated.', $updatedDataImportsCount));
    }
}