<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\DataSetInterface;

class UpdateDataSetTotalRowCommand extends ContainerAwareCommand
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
            ->setName('ur:internal:data-set:update-total-row')
            ->addArgument('dataSetId', InputArgument::REQUIRED, 'Data Set Id')
            ->setDescription('update total row for a data sets');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /**
         * @var Logger $logger
         */
        $logger = $container->get('logger');

        $dataSetId = $input->getArgument('dataSetId');

        /* validate inputs */
        if (!$this->validateInput($dataSetId, $output)) {
            throw new \Exception(sprintf('command run failed: params must integer'));
        }

        $dataSet = $dataSetManager->find($dataSetId);
        //update total rows of connected data source
        if ($dataSet instanceof DataSetInterface) {
            $logger->notice('updating total row for data set');

            $updateTotalRowService = $container->get('ur.service.data_set.update_total_row');
            $updateTotalRowService->updateDataSetTotalRow($dataSetId);

            $logger->notice('success update total row for data set ');
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