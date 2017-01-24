<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Service\StringUtilTrait;

class RemoveIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:integration:remove')
            ->addArgument('canonicalName', InputArgument::OPTIONAL, 'canonicalName of integration need to be remove')
            ->setDescription('Remove Integration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        /* get inputs */
        $canonicalName = $input->getArgument('canonicalName');

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        if ($canonicalName == null) {
            $this->logger->info(sprintf('invalid canonicalName, expected not null'));
            return;
        }

        /** @var IntegrationInterface $integration */
        $integration = $integrationManager->findByCanonicalName($canonicalName);
        if (!($integration instanceof IntegrationInterface)) {
            $this->logger->info(sprintf('not found integration with canonicalName %s', $canonicalName));
            return;
        }

        /* check integration is used by data sources or not */
        $dataSourceIntegrationManager = $container->get('ur.domain_manager.data_source_integration');
        $dataSourceIntegrations = $dataSourceIntegrationManager->findByIntegrationCanonicalName($canonicalName);
        if (!is_array($dataSourceIntegrations) || count($dataSourceIntegrations) > 0) {
            $this->logger->info(sprintf('can not remove integration with canonicalName %s due to some data sources are using this', $canonicalName));
            return;
        }

        /* remove */
        try {
            $integrationManager->delete($integration);
        } catch (\Exception $e) {
            $this->logger->info(sprintf('deleting integrations with canonicalName %s got exception %s', $canonicalName, $e->getMessage()));
        }

        $this->logger->info(sprintf('%d integrations removed.', 1));
    }
}