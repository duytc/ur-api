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

        foreach ($integrationGroups as $integrationGroup) {
            // validate
            if (!is_array($integrationGroup) || !array_key_exists('name', $integrationGroup) || !array_key_exists('integrations', $integrationGroup)) {
                $logger->error('invalid config, missing "name" or "integrations"');
                return;
            }

            // create new entity integrationGroup
            $integrationGroupEntity = (new IntegrationGroup())
                ->setName($integrationGroup['name']);

            // build all entity integrations for integrationGroup
            $integrations = $integrationGroup['integrations'];
            if (!is_array($integrations)) {
                $logger->error('invalid config, "integrations" must be array');
                return;
            }

            $integrationEntities = [];

            foreach ($integrations as $integration) {
                // validate
                if (!is_array($integration) || !array_key_exists('name', $integration) || !array_key_exists('url', $integration) || !array_key_exists('type', $integration)) {
                    $logger->error('invalid config, missing "name" or "url" or "type"');
                    return;
                }

                $integration = (new Integration())
                    ->setName($integration['name'])
                    ->setType($integration['type'])
                    ->setUrl($integration['url'])//->setIntegrationGroup()
                ;

                $integrationEntities[] = $integration;
            }

            $integrationGroupEntity->setIntegrations($integrationEntities);
            $integrationGroupManager->save($integrationGroupEntity);
        }
        $logger->info("command run successful");
    }
} 