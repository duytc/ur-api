<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\DomainManager\IntegrationGroupManagerInterface;
use UR\Entity\Core\Integration;
use UR\Entity\Core\IntegrationGroup;

class CreateIntegrationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:integration:create')
            ->setDescription('Create Integration Group and Integrations belong to its');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $logger = $container->get('logger');

        $builtinIntegrations = $container->getParameter('builtin_integrations');

        /** @var array $integrationGroups */
        $integrationGroups = $builtinIntegrations['integration_groups'];

        /** @var IntegrationGroupManagerInterface $integrationGroupManager */
        $integrationGroupManager = $container->get('ur.domain_manager.integration_group');
        $integrationManager = $container->get('ur.domain_manager.integration');

        foreach ($integrationGroups as $integrationGroup) {
            // validate
            if (!is_array($integrationGroup) || !array_key_exists('name', $integrationGroup) || !array_key_exists('integrations', $integrationGroup)) {
                $logger->error('invalid config, missing "name" or "integrations"');
                return;
            }

            $dbIntegrationGroup = $integrationGroupManager->findByName($integrationGroup['name']);

            if (count($dbIntegrationGroup) < 1) {//doesn't exist in DB - create new entity integrationGroup
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

            $integrationEntities = [];
            $integrationGroupManager->save($integrationGroupEntity);
            foreach ($integrations as $integration) {
                // validate
                if (!is_array($integration) || !array_key_exists('name', $integration) || !array_key_exists('url', $integration) || !array_key_exists('type', $integration)) {
                    $logger->error('invalid config, missing "name" or "url" or "type"');
                    return;
                }

                $dbIntegration = $integrationManager->findByName($integration['name']);

                if (count($dbIntegration) < 1) { //doesn't exist in DB - create new entity integration
                    $integrationEntity = (new Integration())
                        ->setName($integration['name'])
                        ->setType($integration['type'])
                        ->setUrl($integration['url']);
                } else { // update integration if exist
                    $integrationEntity = $dbIntegration[0];
                    $integrationEntity->setType($integration['type']);
                    $integrationEntity->setUrl($integration['url']);
                }

                $integrationEntity->setIntegrationGroup(($integrationGroupEntity));
                $integrationEntities[] = $integrationEntity;
            }

            $integrationGroupEntity->setIntegrations($integrationEntities);
            $integrationGroupManager->save($integrationGroupEntity);
        }

        $logger->info("command run successful");
    }
} 