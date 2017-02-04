<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;

class UpdateShareKeyConfigsCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this
			->setName('ur:share-key:update')
			->setDescription('Update share key configs to new structure');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var ContainerInterface $container */
		$container = $this->getContainer();

		/** @var ReportViewManagerInterface $reportViewManager */
		$reportViewManager = $container->get('ur.domain_manager.report_view');
		$reportViews = $reportViewManager->all();

		$output->writeln('Start refreshing share key configs');
		$count = 0;
		/**
		 * @var ReportViewInterface $reportView
		 */
		foreach($reportViews as $reportView) {
			$shareKeyConfigs = $reportView->getSharedKeysConfig();
			foreach ($shareKeyConfigs as $token=>$config) {
				if (!array_key_exists('fields', $config)) {
					$count++;
					$shareKeyConfigs[$token] = array(
						'fields' => $config,
						'dateRange' => array (
							'startDate' => null,
							'endDate' => null
						)
					);
				}
			}

			$reportView->setSharedKeysConfig($shareKeyConfigs);
			$reportViewManager->save($reportView);
		}

		$output->writeln(sprintf('Finish refreshing share key configs, %d key got updated', $count));
	}
}