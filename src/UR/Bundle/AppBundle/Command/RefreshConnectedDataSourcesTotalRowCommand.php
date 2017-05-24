<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Bundle\ApiBundle\Behaviors\UpdateDataSetTotalRowTrait;
use UR\Model\Core\DataSetInterface;

class RefreshConnectedDataSourcesTotalRowCommand extends ContainerAwareCommand
{
    use UpdateDataSetTotalRowTrait;

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

        $dataSets = $dataSetManager->all();
        $count = 0;
        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $this->updateConnectedDataSourceTotalRow($dataSet);
            $count++;
        }

        $output->writeln(sprintf('command run successfully', $count));
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('doctrine.orm.entity_manager');
    }
}