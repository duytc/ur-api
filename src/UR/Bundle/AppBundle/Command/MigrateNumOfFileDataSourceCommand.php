<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\StringUtilTrait;

class MigrateNumOfFileDataSourceCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:data-source:num-of-file')
            ->setDescription('Migrate num of file for data source');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');

        //get all data source entry
        $dataSources = $this->dataSourceManager->all();

        $output->writeln(sprintf('migrating %d data source num of file', count($dataSources)));

        // migrate connected data source multi date transforms
        $migratedAlertsCount = $this->migrateDataSourceNumOfFile($output, $dataSources);

        $output->writeln(sprintf('command run successfully: %d data source.', $migratedAlertsCount));
    }

    /**
     * update total row
     *
     * @param OutputInterface $output
     * @param array|DataSourceInterface[] $dataSources
     * @return int migrated integrations count
     */
    private function migrateDataSourceNumOfFile(OutputInterface $output, array $dataSources)
    {
        $migratedCount = 0;

        foreach ($dataSources as $dataSource) {
            /*
             * from: 0
             */
            $numOfFile = $dataSource->getNumOfFiles();

            /*
             *  migrate to update total row of file
             */

            if ($numOfFile < 1) {
                try {

                    if (!$dataSource instanceof DataSourceInterface) {
                        continue;
                    }

                    $numOfFile = count($dataSource->getDataSourceEntries());
                    $dataSource->setNumOfFiles($numOfFile);
                    $this->dataSourceManager->save($dataSource);
                    $migratedCount++;
                } catch (Exception $exception) {
                    $this->logger->error($exception->getMessage());
                }
            }
        }

        return $migratedCount;
    }
}