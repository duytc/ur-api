<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;

class MigrateConnectedDataSourceFiltersCommand extends ContainerAwareCommand
{
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
            ->setName('ur:migrate:connected-data-source:filters')
            ->setDescription('Migrate Connected data source Filters: use date format of transform instead filter for the same field');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');

        $this->connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');
        $connectedDataSources = $this->connectedDataSourceManager->all();

        $output->writeln(sprintf('Updating %d connected data source.', count($connectedDataSources)));

        $migratedCount = 0;

        foreach ($connectedDataSources as $connectedDataSource) {

            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                return;
            }

            $filters = $connectedDataSource->getFilters();
            $transforms = $connectedDataSource->getTransforms();
            $mapFields = $connectedDataSource->getMapFields();

            if (empty($filters) && !is_array($filters)) {
                continue;
            }

            $replaceFormatValue = false;
            foreach ($filters as &$filter) {
                if ($filter['type'] == 'date') {
                    $field = '';
                    foreach ($mapFields as $mapFieldKey => $mapFieldValue) {
                        if ($filter['field'] == $mapFieldKey) $field = $mapFieldValue;
                    }

                    foreach ($transforms as $transform) {
                        if ($transform['field'] == $field) {
                            $replaceFormatValue = true;

                            unset($filter['format']);
                            unset($filter['isPartialMatch']);

                            $transformFrom = $transform['from'][0];
                            $transformFrom['isCustomFormatDateFrom'] = false;
                            $filter['formats'] = $transformFrom;
                        }
                    }
                }
            }

            if ($replaceFormatValue) {
                    $migratedCount++;
                    $connectedDataSource->setFilters($filters);
                    $this->connectedDataSourceManager->save($connectedDataSource);
            }
        }

        $output->writeln(sprintf('The command runs successfully: %d connected data sources updated.', $migratedCount));
    }
}