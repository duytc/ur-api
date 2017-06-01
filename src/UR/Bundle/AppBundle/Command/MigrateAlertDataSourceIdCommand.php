<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\AlertDTOInterface;
use UR\Service\StringUtilTrait;

class MigrateAlertDataSourceIdCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var AlertManagerInterface
     */
    private $alertManager;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:alert:data-source-id')
            ->setDescription('Migrate alert to have data source id. Old data source id is included in details, now extract to a field.');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->alertManager = $container->get('ur.domain_manager.alert');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');

        $alerts = $this->alertManager->all();

        $output->writeln(sprintf('update %d alert code to latest format', count($alerts)));

        // migrate alert params
        $migratedAlertsCount = $this->migrateAlertDataSourceId($output, $alerts);

        $output->writeln(sprintf('command run successfully: %d Alerts updated.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|AlertInterface[] $alerts
     * @return int migrated integrations count
     */
    private function migrateAlertDataSourceId(OutputInterface $output, array $alerts)
    {
        $migratedCount = 0;

        foreach ($alerts as $alert) {
            /* skip updating if current dataSourceId column is already not null */
            if ($alert->getDataSource() instanceof DataSourceInterface) {
                continue;
            }

            /*
             * dataSourceId from details:
             * details: {
             *     "detail":"File \"sample1-copy.csv\" from data source datasource-1 has been successfully imported to data set \"dataset-1-datasource-1\".",
             *     "dataSourceId":12,
             *     "dataSourceName":"datasource-1",
             *     "dataSetId":23,
             *     "dataSetName":"dataset-1-datasource-1",
             *     "fileName":"sample1-copy.csv",
             *     "importId":349
             * }
             */
            $details = $alert->getDetail();

            /*
             * extract dataSourceId
             */
            if (!is_array($details) || !array_key_exists(AlertDTOInterface::DATA_SOURCE_ID, $details)) {
                continue;
            }

            /** @var int $dataSourceId */
            $dataSourceId = $details[AlertDTOInterface::DATA_SOURCE_ID];

            /* find data source by id */
            /** @var DataSourceInterface $dataSource */
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $migratedCount++;

            /* update */
            $alert->setDataSource($dataSource);
            $this->alertManager->save($alert);
        }

        return $migratedCount;
    }
}