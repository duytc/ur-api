<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationGroupManagerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Entity\Core\Integration;
use UR\Entity\Core\IntegrationGroup;
use UR\Model\Core\IntegrationGroupInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Service\StringUtilTrait;

class CreateIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    protected function configure()
    {
        $this
            ->setName('ur:integration:create')
            ->setDescription('Create Integration Group and Integrations belong to its');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $logger = $container->get('logger');

        $logger->info("starting command...");
        $logger->info("reading builtin_integrations config...");

        /* get builtin integration config
         * sample config:
         * integration_groups:
         *     -
         *         name: 'Demand Partner'
         *         integrations:
         *             -
         *                 name: 'Spotx  api'
         *                 url: 'http://spotx.com/api'
         *                 type: csv
         *             -
         *                 name: 'Spotx selenium 1'
         *                 url: 'http://spotx.com/selenium'
         *                 type: excel
         *     -
         *         ...
         */
        $builtinIntegrations = $container->getParameter('builtin_integrations');
        if (!is_array($builtinIntegrations) || !array_key_exists('integration_groups', $builtinIntegrations)) {
            $logger->error('invalid config, config is not an array or missing "integration_groups"');
            return;
        }

        /** @var array $integrationGroups */
        $integrationGroups = $builtinIntegrations['integration_groups'];
        if (!is_array($integrationGroups)) {
            $logger->error('invalid config, "integration_groups" is not an array');
            return;
        }

        /** @var IntegrationGroupManagerInterface $integrationGroupManager */
        $integrationGroupManager = $container->get('ur.domain_manager.integration_group');
        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');

        // parse and sync config to database
        $logger->info("parsing and sync config...");
        $insertedIntegrations = 0;
        $updatedIntegrations = 0;

        foreach ($integrationGroups as $integrationGroup) {
            // validate
            if (!is_array($integrationGroup) || !array_key_exists('name', $integrationGroup) || !array_key_exists('integrations', $integrationGroup)) {
                $logger->error('invalid config, missing "name" or "integrations" under "integration_groups"');
                return;
            }

            // process integrationGroup
            /** @var IntegrationGroupInterface $integrationGroupEntity */
            $integrationGroupEntity = null;
            $dbIntegrationGroup = $integrationGroupManager->findByName($integrationGroup['name']);

            if (count($dbIntegrationGroup) < 1) { //doesn't exist in DB - create new entity integrationGroup
                $integrationGroupEntity = (new IntegrationGroup())
                    ->setName($integrationGroup['name']);
            } else {
                $integrationGroupEntity = $dbIntegrationGroup[0];
            }

            // build all entity integrations for integrationGroup
            $integrations = $integrationGroup['integrations'];
            if (!is_array($integrations)) {
                $logger->error('invalid config, "integrations" must be array');
                return;
            }

            /** @var IntegrationInterface[] $integrationEntities */
            $integrationEntities = [];
            $integrationGroupManager->save($integrationGroupEntity);
            foreach ($integrations as $integration) {
                // validate
                if (!is_array($integration) || !array_key_exists('name', $integration) || !array_key_exists('url', $integration) || !array_key_exists('type', $integration)) {
                    $logger->error('invalid config, missing "name" or "url" or "type"');
                    return;
                }

                $canonicalName = $this->normalizeName($integration['name']);
                /** @var IntegrationInterface $dbIntegration */
                $dbIntegration = $integrationManager->findByCanonicalName($canonicalName);
                /** @var IntegrationInterface $integrationEntity */
                $integrationEntity = null;

                if (!($dbIntegration instanceof IntegrationInterface)) { //doesn't exist in DB - create new entity integration
                    $integrationEntity = (new Integration())
                        ->setName($integration['name'])
                        ->setType($integration['type'])
                        ->setUrl($integration['url']);

                    // count inserted integrations
                    $insertedIntegrations++;
                } else { // update integration if exist
                    $integrationEntity = $dbIntegration
                        ->setType($integration['type'])
                        ->setUrl($integration['url']);

                    // count updated integrations
                    $updatedIntegrations++;
                }

                $integrationEntity->setIntegrationGroup(($integrationGroupEntity));
                $integrationEntities[] = $integrationEntity;
            }

            $integrationGroupEntity->setIntegrations($integrationEntities);
            $integrationGroupManager->save($integrationGroupEntity);
        }

        $logger->info(sprintf("command run successfully: %d created and %d updated integrations.", $insertedIntegrations, $updatedIntegrations));
    }
}