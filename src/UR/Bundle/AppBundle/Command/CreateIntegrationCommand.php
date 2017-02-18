<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Entity\Core\Integration;
use UR\Model\Core\IntegrationInterface;
use UR\Service\StringUtilTrait;

class CreateIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:integration:create')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Integration name')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL,
                'Integration parameters (optional), allow multiple parameters separated by comma, e.g. -p "username,password"')
            ->setDescription('Create or update Integration with name and parameters');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /* get inputs */
        $name = $input->getArgument('name');
        $paramsString = $input->getOption('parameters');

        if (!$this->validateInput($name, $paramsString)) {
            return;
        }

        // parse params from paramsString
        $params = null;
        if (!empty($paramsString)) {
            $params = explode(',', $paramsString);

            // trim
            $params = array_map(function ($param) {
                return trim($param);
            }, $params);
        }

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');

        $cName = $this->normalizeName($name);
        $integration = $integrationManager->findByCanonicalName($cName);

        $isFoundIntegration = ($integration instanceof IntegrationInterface);
        if (!$isFoundIntegration) {
            $integration = new Integration();
        }

        $integration
            ->setName($name)
            ->setParams($params);

        $integrationManager->save($integration);

        $output->writeln(sprintf('command run successfully: %d %s.', 1, ($isFoundIntegration ? 'updated' : 'created')));
    }

    /**
     * @param string $name
     * @param string $paramsString
     * @return bool
     */
    private function validateInput($name, $paramsString)
    {
        if ($name == null | empty($name)) {
            $this->logger->info(sprintf('command run failed: name must not be null or empty'));
            return false;
        }

        // TODO: validate allowed characters in params

        return true;
    }
}