<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSet\UpdateNumOfPendingLoad;

class MigrateNumOfPendingLoadDataSetCommand extends ContainerAwareCommand
{
    /** @var  Logger */
    private $logger;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  UpdateNumOfPendingLoad */
    private $updateNumOfPendingLoad;

    /** @var  EntityManagerInterface */
    private $em;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:data-set:num-of-pending-load')
            ->setDescription('Migrate num of pending load for data set');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->updateNumOfPendingLoad = $container->get('ur.service.data_set.update_num_of_pending_load');
        $this->em = $container->get('doctrine.orm.entity_manager');

        $sync = new Synchronizer($this->em->getConnection(), new Comparator());
        $sync->createColumnNumOfPendingLoad('core_data_set', 'num_of_pending_load', Type::getType(Type::INTEGER), array('unsigned' => true, 'notnull' => true));

        //get all data sets
        $dataSets = $this->dataSetManager->all();

        $output->writeln(sprintf('migrating %d data set num of pending load', count($dataSets)));

        $migrateCount = $this->migrateDataSetNumOfPendingLoad($output, $dataSets);

        $output->writeln(sprintf('command run successfully: %d data sets', $migrateCount));
    }

    /**
     * update number of pending load
     *
     * @param OutputInterface $output
     * @param array|DataSetInterface[] $dataSets
     * @return int migrated dataSets count
     */
    private function migrateDataSetNumOfPendingLoad(OutputInterface $output, array $dataSets)
    {
        if (!is_array($dataSets)) {
            return 0;
        }

        $migrateCount = 0;
        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }
            $message = sprintf('Migrate number of pending load for dataSet %s, id %s', $dataSet->getName(), $dataSet->getId());
            $output->writeln($message);
            $this->logger->info($message);
            if ($this->updateNumOfPendingLoad->updateNumberOfPendingLoadForDataSet($dataSet, $this->em)) {
                $migrateCount++;
            };
        }
        return $migrateCount;
    }
}