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
			->addOption('name', 'i', InputOption::VALUE_REQUIRED, 'Integration name')
			->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Integration type: ui or api')
			->addOption('method', 'm', InputOption::VALUE_REQUIRED, 'Integration method: GET or POST')
			->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'Integration url')
			->setDescription('Create Integration');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var ContainerInterface $container */
		$container = $this->getContainer();
		/** @var Logger $logger */
		$this->logger = $container->get('logger');

		$this->logger->info('starting command...');

		/* get inputs */
		$name = $input->getOption('name');
		$type = $input->getOption('type');
		$method = $input->getOption('method');
		$url = $input->getOption('url');

		if (!$this->validateInput($name, $type, $method, $url)) {
			return;
		}

		if (!$this->validateInput($name, $type, $method, $url)) {
			return;
		}

		/** @var IntegrationManagerInterface $integrationManager */
		$integrationManager = $container->get('ur.domain_manager.integration');

		$cName = $this->normalizeName($name);
		$integration = $integrationManager->findByCanonicalName($cName);

		if (empty($integration)) {
			$integration = new Integration();
		}

		$integration->setName($name)
			->setType($type)
			->setUrl($url)
			->setMethod($method);

		$integrationManager->save($integration);

		$this->logger->info(sprintf('command run successfully: %d created.', 1));
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @param string $method
	 * @param string $url
	 * @return bool
	 */
	private function validateInput($name, $type, $method, $url)
	{
		if ($name == null || $type == null || $method == null || $url == null) {
			$this->logger->info(sprintf('command run failed: input must not be null or empty'));
			return false;
		}

		if (!in_array($type, Integration::supportedTypes())) {
			$this->logger->info(sprintf('command run failed: type %s not supported', $type));
			return false;
		}

		if (!in_array($method, Integration::supportedMethods())) {
			$this->logger->info(sprintf('command run failed: method %s not supported', $method));
			return false;
		}

		// validate url format
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			$this->logger->info(sprintf('command run failed: url %s malformed', $url));
			return false;
		}

		return true;
	}
}