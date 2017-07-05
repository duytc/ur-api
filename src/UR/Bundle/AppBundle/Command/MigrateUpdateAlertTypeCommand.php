<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\Core\AlertInterface;
use UR\Repository\Core\AlertRepository;
use UR\Service\StringUtilTrait;

class MigrateUpdateAlertTypeCommand extends ContainerAwareCommand
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
            ->setName('ur:migrate:alert:type:update')
            ->setDescription('Migrate alert type for each record from alert table base on code');
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

        $output->writeln(sprintf('update %d alert type ', count($alerts)));

        // migrate alert params
        $migratedAlertsCount = $this->migrateUpdateAlertType($output, $alerts);

        $output->writeln(sprintf('command run successfully: %d alerts.', $migratedAlertsCount));
    }

    /**
     * migrate Update Alert Type
     *
     * @param OutputInterface $output
     * @param array|AlertInterface[] $alerts
     * @return int migrated update alert type count
     */
    private function migrateUpdateAlertType(OutputInterface $output, array $alerts)
    {
        $migratedCount = 0;

        foreach ($alerts as $alert) {
            /*
             * from code: 200, 201, 202 ...
             */
            $code = $alert->getCode();

            /*
             * migrate update alert type
             */
            $type = array_key_exists($code, Alert::$ALERT_CODE_TO_TYPE_MAP) ? Alert::$ALERT_CODE_TO_TYPE_MAP[$code] : Alert::ALERT_TYPE_INFO;
            $migratedCount++;

            $alert->setType($type);
            $this->alertManager->save($alert);
        }

        return $migratedCount;
    }
}