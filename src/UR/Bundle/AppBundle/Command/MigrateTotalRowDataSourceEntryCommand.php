<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use \Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DataSource\DataSourceType;
use UR\Service\StringUtilTrait;

class MigrateTotalRowDataSourceEntryCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /**
     * @var DataSourceFileFactory
     */
    private $dataSourceFileFactory;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:data-source-entry:total-row')
            ->setDescription('Migrate total row for data source entry');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSourceEntryManager = $container->get('ur.domain_manager.data_source_entry');
        $this->dataSourceFileFactory = $container->get('ur.service.file_factory');

        //get all data source entry
        $dataSourceEntries = $this->dataSourceEntryManager->all();

        $output->writeln(sprintf('migrating %d data source entry total row', count($dataSourceEntries)));

        // migrate connected data source multi date transforms
        $migratedAlertsCount = $this->migrateEntryTotalRow($output, $dataSourceEntries);

        $output->writeln(sprintf('command run successfully: %d data source entry.', $migratedAlertsCount));
    }

    /**
     * update total row
     *
     * @param OutputInterface $output
     * @param array|DataSourceEntryInterface[] $dataSourceEntries
     * @return int migrated integrations count
     */
    private function migrateEntryTotalRow(OutputInterface $output, array $dataSourceEntries)
    {
        $migratedCount = 0;

        foreach ($dataSourceEntries as $dataSourceEntry) {
            /*
             * from: 0
             */
            $totalRow = $dataSourceEntry->getTotalRow();

            /*
             *  migrate to update total row of file
             */

            if ($totalRow < 1) {
                try {
                    $dataSource = $dataSourceEntry->getDataSource();

                    if (!$dataSource instanceof DataSourceInterface) {
                        continue;
                    }
                    $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($this->dataSourceFileFactory->getSourceExtension($dataSourceEntry->getPath()));
                    $dataSourceFile = $this->dataSourceFileFactory->getFile($dataSourceTypeExtension, $dataSourceEntry->getPath(), $dataSource->getSheets());
                    $totalRow = $dataSourceFile->getTotalRows($dataSource->getSheets());
                    $dataSourceEntry->setTotalRow($totalRow);
                    $this->dataSourceEntryManager->save($dataSourceEntry);
                    $migratedCount++;
                } catch (Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        }

        return $migratedCount;
    }
}