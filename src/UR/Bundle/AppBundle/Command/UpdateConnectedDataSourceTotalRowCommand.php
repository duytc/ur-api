<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\ConnectedDataSourceInterface;

class UpdateConnectedDataSourceTotalRowCommand extends ContainerAwareCommand
{
    const SQL_TYPE_LONGTEXT = 'longtext';
    const SQL_TYPE_TEXT = 'text';
    const SQL_TYPE_VARCHAR = 'string';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:internal:connected-data-source:update-total-row')
            ->addArgument('connectedDataSourceId', InputArgument::REQUIRED, 'Connected Data Source Id')
            ->setDescription('update total row for connected data sources belong to a data set');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');

        /**
         * @var Logger $logger
         */
        $logger = $container->get('logger');

        $connectedDataSourceId = $input->getArgument('connectedDataSourceId');

        /* validate inputs */
        if (!$this->validateInput($connectedDataSourceId, $output)) {
            throw new \Exception(sprintf('command run failed: params must integer'));
        }

        $connectedDataSource = $connectedDataSourceManager->find($connectedDataSourceId);
        // update total rows of connected data source
        if ($connectedDataSource instanceof ConnectedDataSourceInterface) {
            $logger->notice('updating total row for connected data sources');

            $updateTotalRowService = $this->getContainer()->get('ur.service.data_set.update_total_row');
            $updateTotalRowService->updateConnectedDataSourceTotalRow($connectedDataSource);

            $logger->notice('success update total row connected data sources ');
        }
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * @param $dataSetId
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput($dataSetId, OutputInterface $output)
    {
        function isInteger($input)
        {
            return (ctype_digit(strval($input)));
        }

        // validate input
        if (!isInteger($dataSetId)) {
            $output->writeln(sprintf('command run failed: params must be an integer'));
            return false;
        }
        return true;
    }
}