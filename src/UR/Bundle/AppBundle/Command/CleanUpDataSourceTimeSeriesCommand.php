<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\CleanUpDataSourceTimeSeriesService;

class CleanUpDataSourceTimeSeriesCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:data-source:remove-duplicated-dates';
    const ARGUMENT_DATA_SOURCE_ID = "dataSource";

    /** @var  CleanUpDataSourceTimeSeriesService */
    protected $cleanUpDataSourceTimeSeriesService;

    protected $dataSource;

    /** @var  DataSourceManagerInterface */
    protected $dataSourceManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(self::ARGUMENT_DATA_SOURCE_ID, InputOption::VALUE_REQUIRED, 'Id of data source')
            ->setDescription('Command to remove data + file history for a data source by date range');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');
        $this->cleanUpDataSourceTimeSeriesService = $container->get('ur.service.data_source.clean_up_data_source_time_series_service');
        
        if (!$this->validateInput($input, $output)) {
            $output->writeln('Quit command');
            return;
        }

        $this->cleanUpDataSourceTimeSeriesService->cleanUpDataSourceTimeSeries($this->dataSource);
        $output->writeln('Command run success');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput(InputInterface $input, OutputInterface $output)
    {
        $dataSourceId = $input->getArgument(self::ARGUMENT_DATA_SOURCE_ID);
        $this->dataSource = $this->dataSourceManager->find($dataSourceId);

        if (!$this->dataSource instanceof DataSourceInterface) {
            $output->writeln(sprintf('<error>command run failed: not found any data source with id: %s</error>', $dataSourceId));
            return false;
        }

        return true;
    }
}