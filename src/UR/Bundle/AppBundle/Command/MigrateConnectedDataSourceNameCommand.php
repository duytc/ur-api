<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\StringUtilTrait;

class MigrateConnectedDataSourceNameCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:connected-data-source:name')
            ->setDescription('Migrate Connected Data Source name to use data source name if empty');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');

        $output->writeln('Updating name for Connected Data Source');

        $this->connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');
        $connectedDataSources = $this->connectedDataSourceManager->all();

        // migrate name
        $migratedCount = $this->migrateConnectedDataSourceName($connectedDataSources);

        $output->writeln(sprintf('Command run successfully: %d Connected Data Source updated.', $migratedCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateConnectedDataSourceName(array $connectedDataSources)
    {
        $migratedCount = 0;

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            if (null !== $connectedDataSource->getName() && '' !== $connectedDataSource->getName()) {
                continue;
            }

            $connectedDataSource->setName($connectedDataSource->getDataSource()->getName());
            $this->connectedDataSourceManager->save($connectedDataSource);

            $migratedCount++;
        }

        return $migratedCount;
    }
}