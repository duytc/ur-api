<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Service\StringUtilTrait;

class RegenerateApiKeyCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:ur-api-key:regenerate')
            ->addArgument('id', InputArgument::REQUIRED, 'DataSource Id')
            ->setDescription('Regenerate Unified Report Api Key in DataSource');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /* get inputs */
        $id = $input->getArgument('id');

        if (!$this->validateInput($id)) {
            return;
        }

        $regenerateApiKeyService = $container->get('ur.service.datasource.regenerate_api_key');
        $result = $regenerateApiKeyService->regenerateUrApiKey($id);

        if (!$result) {
            $this->logger->info(sprintf('command run failed: resource not found'));
        } else {
            $this->logger->info(sprintf('command run successfully: %d updated.', 1));
        }
    }

    /**
     * @param string $id
     * @return bool
     */
    private function validateInput($id)
    {
        if ($id == null) {
            $this->logger->info(sprintf('command run failed: input must not be null or empty'));
            return false;
        }

        if (!is_numeric($id)) {
            $this->logger->info(sprintf('command run failed: input must be number'));
            return false;
        }

        return true;
    }
}