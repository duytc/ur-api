<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\UpdateImportTableAddGroupByDateColumn;
use UR\Service\StringUtilTrait;

class MigrateImportTableAddGroupByDateColumnCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-table:add-groupby-date-columns')
            ->addOption('all-datasets', 'a', InputOption::VALUE_NONE,
                'Enable for all users')
            ->addOption('dataset', 'd', InputOption::VALUE_OPTIONAL,
                'Enable for all users')
            ->setDescription('Add column __unique_id for data import table if not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');
        $this->logger->info('starting command...');

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.entity_manager');

        /** @var IntegrationManagerInterface $integrationManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        $service = new UpdateImportTableAddGroupByDateColumn($em, $this->logger);

        $success = false;

        $isAllPublisherOption = $input->getOption('all-datasets');
        $dataSetId = $input->getOption('dataset');

        if (!$this->validateInput($output, $isAllPublisherOption, $dataSetId)){
            return;
        }

        if ($isAllPublisherOption) {
            $allDataSets = $dataSetManager->all();
            foreach ($allDataSets as $dataSet) {
                if ($dataSet instanceof DataSetInterface) {
                    $success = $service->updateDataSet($dataSet);
                }
            }
        } else {

            /**@var DataSetInterface $dataSet */
            $dataSet = $dataSetManager->find($dataSetId);
            $success = $service->updateDataSet($dataSet);
        }
        if ($success) {
            $this->logger->info(sprintf('command run successfully'));
        } else {
            $this->logger->info(sprintf('command run not successfully'));
        }
    }

    /**
     * @param OutputInterface $output
     * @param $isAllPublisherOption
     * @param $dataSetOption
     * @return bool
     */
    private function validateInput($output, $isAllPublisherOption, $dataSetOption){
        if (!$isAllPublisherOption && !$dataSetOption){
            $output->writeln('Use option -a or -d with dataSourceId. Try one of them');
            return false;
        }

        if ($isAllPublisherOption && $dataSetOption){
            $output->writeln('Can not use option -a and -d together. Try one of them');
            return false;
        }

        return true;
    }
}