<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceIntegrationManagerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Entity\Core\Integration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Service\StringUtilTrait;

class MigrateIntegrationParamsCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var IntegrationManagerInterface
     */
    private $integrationManager;

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
            ->setName('ur:migrate:integration:params')
            ->setDescription('Migrate Integration params to latest format, also migrate DataSourceIntegration params');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->integrationManager = $container->get('ur.domain_manager.integration');
        $this->dataSourceIntegrationManager = $container->get('ur.domain_manager.data_source_integration');

        $integrations = $this->integrationManager->all();
        $dataSourceIntegrations = $this->dataSourceIntegrationManager->all();

        $output->writeln(sprintf('migrating %d integrations to latest format', count($integrations)));

        // migrate integrations params
        $migratedIntegrationsCount = $this->migrateIntegrationParams($output, $integrations);

        // migrate dataSourceIntegrations params
        $migratedDataSourceIntegrationsCount = $this->migrateDataSourceIntegrationParams($output, $dataSourceIntegrations);

        $output->writeln(sprintf('command run successfully: %d Integrations updated, %d DataSourceIntegrations updated.', $migratedIntegrationsCount, $migratedDataSourceIntegrationsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|IntegrationInterface[] $integrations
     * @return int migrated integrations count
     */
    private function migrateIntegrationParams(OutputInterface $output, array $integrations)
    {
        $migratedCount = 0;

        foreach ($integrations as $integration) {
            /*
             * old format:
             * [
             *     <param name 1>,
             *     <param name 2>,
             *     ...
             * ]
             */
            $params = $integration->getParams();

            /*
             * migrate to new format:
             * [
             *     [ key => <param name 1>, type => <param type 1> ],
             *     [ key => <param name 2>, type => <param type 2> ],
             *     ...
             * ]
             */
            $newParams = [];
            $migrated = false;
            foreach ($params as $param) {
                if (!is_array($param)) {
                    $newParams[] = [
                        Integration::PARAM_KEY_KEY => $param,
                        Integration::PARAM_KEY_TYPE => Integration::PARAM_TYPE_PLAIN_TEXT // default
                    ];

                    $migrated = true;
                }
            }

            if (!$migrated) {
                continue;
            }

            $migratedCount++;
            $integration->setParams($newParams);
            $this->integrationManager->save($integration);
        }

        return $migratedCount;
    }

    /**
     * migrate DataSourceIntegrations to latest format
     *
     * @param OutputInterface $output
     * @param array|DataSourceIntegrationInterface[] $dataSourceIntegrations
     * @return int migrated integrations count
     */
    private function migrateDataSourceIntegrationParams(OutputInterface $output, array $dataSourceIntegrations)
    {
        $migratedCount = 0;

        foreach ($dataSourceIntegrations as $dataSourceIntegration) {
            /*
             * old format:
             * [
             *     [ key => <param name 1>, value => <param value 1> ],
             *     [ key => <param name 2>, value => <param value 2> ],
             *     ...
             * ]
             */
            $params = $dataSourceIntegration->getOriginalParams(); // be sure get original params, not params that don't have real secure value

            /*
             * migrate to new format:
             * [
             *     [ key => <param name 1>, type => <param type 1>, value => <param value 1> ],
             *     [ key => <param name 2>, type => <param type 2>, value => <param value 2> ],
             *     ...
             * ]
             */
            $newParams = [];
            $migrated = false;
            foreach ($params as $param) {
                // do not update if not array
                if (!is_array($param)) {
                    continue;
                }

                $newParam = array_merge($param, [
                    Integration::PARAM_KEY_TYPE => Integration::PARAM_TYPE_PLAIN_TEXT // default
                ]);

                $newParams[] = $newParam;

                $migrated = true;
            }

            if (!$migrated) {
                continue;
            }

            $migratedCount++;
            $dataSourceIntegration->setParams($newParams);
            $this->dataSourceIntegrationManager->save($dataSourceIntegration);
        }

        return $migratedCount;
    }
}