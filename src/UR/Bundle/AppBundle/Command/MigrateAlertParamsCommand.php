<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\StringUtilTrait;

class MigrateAlertParamsCommand extends ContainerAwareCommand
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
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:alert:params')
            ->setDescription('Migrate alert params to latest format');
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

        $alerts = $this->alertManager->all();

        $output->writeln(sprintf('migrating %d alerts to latest format', count($alerts)));

        // migrate alert params
        $migratedAlertsCount = $this->migrateIntegrationParams($output, $alerts);

        $output->writeln(sprintf('command run successfully: %d Alerts.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|AlertInterface[] $alerts
     * @return int migrated integrations count
     */
    private function migrateIntegrationParams(OutputInterface $output, array $alerts)
    {
        $migratedCount = 0;

        foreach ($alerts as $alert) {
            /*
             * from format: string
             */
            $details = $alert->getDetail();

            /*
             * migrate to new format:
             * [
             *     [ details => <details string>,],
             *     ...
             * ]
             */
            $newDetails = [];

            if (!is_array($details) || !array_key_exists(AbstractConnectedDataSourceAlert::DETAILS, $details)) {
                $newDetails = [AbstractConnectedDataSourceAlert::DETAILS => $details];
            }

            $migratedCount++;
            $alert->setDetail($newDetails);
            $this->alertManager->save($alert);
        }

        return $migratedCount;
    }
}