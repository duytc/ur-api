<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\DataSetInterface;

class RefreshConnectedDataSourcesTotalRowForAllDataSetCommand extends ContainerAwareCommand
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
            ->setName('ur:connected-data-source:refresh-total-row')
            ->setDescription('Refresh total row for all connected data source');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dataSetManager = $this->getContainer()->get('ur.domain_manager.data_set');
        $updateTotalRowService = $this->getContainer()->get('ur.service.data_set.update_total_row');

        $dataSets = $dataSetManager->all();
        $count = 0;
        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $updateTotalRowService->updateAllConnectedDataSourcesTotalRowInOneDataSet($dataSet->getId());
            $count++;
        }

        $output->writeln(sprintf('command run successfully', $count));
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('doctrine.orm.entity_manager');
    }
}