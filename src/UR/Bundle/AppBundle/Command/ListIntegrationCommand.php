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

class ListIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:integration:list')
            ->addArgument('search', InputArgument::OPTIONAL, 'search by name')
            ->setDescription('List Integration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        /* get inputs */
        $search = $input->getArgument('search');

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        $integrations = ($search == null)
            ? $integrationManager->all()
            : $integrationManager->findByName($search);

        $this->logger->info(sprintf('%d integrations found.', count($integrations)));
        $this->prettyPrint($integrations);
    }

    /**
     * @param array|IntegrationInterface[] $integrations
     */
    private function prettyPrint(array $integrations)
    {
        $this->logger->info(sprintf('-- # -- name --------- c-name ---------- type ---------- method ---------- url ---------- '));
        $this->logger->info(sprintf('|      |              |                 |               |                 |'));

        $i = 0;
        foreach ($integrations as $integration) {
            $i++;

            $this->logger->info(sprintf('-- %d -- %s --------- %s ---------- %s ---------- %s ---------- %s ---------- ',
                $i,
                $integration->getName(),
                $integration->getCanonicalName(),
                $integration->getType(),
                $integration->getMethod(),
                $integration->getUrl()
            ));

            $this->logger->info(sprintf('|      |              |                 |               |                 |'));
        }
    }
}