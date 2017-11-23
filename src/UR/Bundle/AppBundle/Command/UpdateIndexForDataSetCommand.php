<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ReportViewDataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Service\DataSet\DataSetTableUtilInterface;

class UpdateIndexForDataSetCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:data-set:update-indexes';

    /** @var  Logger */
    private $logger;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  ReportViewDataSetManagerInterface */
    private $reportViewDataSetManager;

    /** @var  DataSetTableUtilInterface */
    private $dataSetTableUtil;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Update indexes for data set tables');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);

        /** Services */
        $this->logger = $container->get('logger');
        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->reportViewDataSetManager = $container->get('ur.domain_manager.report_view_data_set');
        $this->dataSetTableUtil = $container->get('ur.service.data_set.table_util');

        $dataSets = $this->dataSetManager->all();

        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $io->section(sprintf("Delete old and then update index for data set %s, id: %s", $dataSet->getName(), $dataSet->getId()));
            try {
                $this->dataSetTableUtil->updateIndexes($dataSet);    
            } catch (\Exception $e) {

            }
        }

        $io->success('Command run successfully. Quit command');
    }
}