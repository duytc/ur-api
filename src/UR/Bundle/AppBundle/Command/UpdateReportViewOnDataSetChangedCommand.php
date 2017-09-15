<?php


namespace UR\Bundle\AppBundle\Command;


use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;
use UR\Service\Report\ReportViewUpdater;

class UpdateReportViewOnDataSetChangedCommand extends ContainerAwareCommand
{
	const COMMAND_NAME = 'ur:report-view:update-on-data-set-changed';

	/** @var  DataSetManagerInterface */
	protected $dataSetManager;

	/** @var  ReportViewUpdater */
	protected $reportViewUpdater;

	/** @var  ReportViewRepositoryInterface */
	protected $reportViewRepository;

	/** @var  Logger */
	private $logger;

	protected function configure()
	{
		$this
			->setName(self::COMMAND_NAME)
			->setDescription('Update report views on data set changed');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var ContainerInterface $container */
		$container = $this->getContainer();

		/** Get services */
		$this->logger = $container->get('logger');
		$this->dataSetManager = $container->get('ur.domain_manager.data_set');
		$this->reportViewUpdater = $container->get('ur.services.report.report_updater');
		$this->reportViewRepository = $container->get('ur.repository.report_view');

		//get all data set entry
		$dataSets = $this->dataSetManager->all();

		$output->writeln(sprintf('Migrating all fields'));

		foreach ($dataSets as $dataSet) {
			if (!$dataSet instanceof DataSetInterface) {
				continue;
			}

			$reportViews = $this->reportViewRepository->getReportViewThatUseDataSet($dataSet);

			/** @var ReportViewInterface $reportView */
			foreach ($reportViews as $reportView) {
				$this->reportViewUpdater->refreshSingleReportView($reportView, $dataSet);
			}
		}

		$output->writeln(sprintf('command run successfully'));
	}
}