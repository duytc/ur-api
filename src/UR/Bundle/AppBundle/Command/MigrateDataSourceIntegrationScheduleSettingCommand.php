<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceIntegrationManagerInterface;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Service\StringUtilTrait;

class MigrateDataSourceIntegrationScheduleSettingCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var DataSourceIntegrationManagerInterface
     */
    private $dataSourceIntegrationManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:data-source-integration:schedule-setting')
            ->setDescription('Migrate DataSourceIntegration schedule setting to latest format');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSourceIntegrationManager = $container->get('ur.domain_manager.data_source_integration');

        $dataSourceIntegrations = $this->dataSourceIntegrationManager->all();

        $output->writeln(sprintf('migrating %d dataSourceIntegrations schedule to latest format', count($dataSourceIntegrations)));

        // migrate dataSourceIntegrations schedule setting
        $migratedDataSourceIntegrationsCount = $this->migrateDataSourceIntegrationSchedule($output, $dataSourceIntegrations);

        $output->writeln(sprintf('command run successfully: %d DataSourceIntegrations updated.', $migratedDataSourceIntegrationsCount));
    }

    /**
     * migrate DataSourceIntegrations to latest format
     *
     * @param OutputInterface $output
     * @param array|DataSourceIntegrationInterface[] $dataSourceIntegrations
     * @return int migrated integrations count
     */
    private function migrateDataSourceIntegrationSchedule(OutputInterface $output, array $dataSourceIntegrations)
    {
        $migratedCount = 0;

        foreach ($dataSourceIntegrations as $dataSourceIntegration) {
            /*
             * old format:
             * 3
             * where 3 is hours for checking every
             */
            $oldSchedule = $dataSourceIntegration->getSchedule();

            if (!is_integer($oldSchedule)) {
                continue;
            }

            /*
             * migrate to new format:
             * {
             *     checked: "checkEvery | checkAt",
             *     checkEvery: { hour: 3 },
             *     checkAt: [
             *         { timeZone: "UTC", hour: 3, minute: 4 },
             *         ...
             *     ]
             * }
             */
            $newSchedule = [
                DataSourceIntegration::SCHEDULE_KEY_CHECKED => DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY, // default
                DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY => [
                    DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR => $oldSchedule
                ]
            ];

            $migratedCount++;
            $dataSourceIntegration->setSchedule($newSchedule);
            $this->dataSourceIntegrationManager->save($dataSourceIntegration);
        }

        return $migratedCount;
    }
}