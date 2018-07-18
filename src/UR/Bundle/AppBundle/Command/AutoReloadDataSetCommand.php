<?php


namespace UR\Bundle\AppBundle\Command;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;
use UR\Service\DataSet\ReloadParams;
use UR\Service\DataSet\ReloadParamsInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\RedLock;
use UR\Worker\Manager;

class AutoReloadDataSetCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:data-set:augmentation:auto-reload';

    /** @var Manager $workerManager */
    private $workerManager;

    /** @var DataSetManagerInterface $dataSetManager */
    private $dataSetManager;

    /** @var Synchronizer */
    private $dataSetSynchronizer;

    /** @var LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository */
    private $linkedMapDataSetRepository;

    /** @var RedLock */
    private $redLock;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('This command automatically reloads master (augmentation) data set if its data set\'s data map has any changed');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);

        $io->section(sprintf('Starting command auto reload master data set...'));

        $this->workerManager = $container->get('ur.worker.manager');
        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->redLock = $container->get('ur.service.red_lock');
        $expiry_time_for_lock = $container->getParameter('ur.redis.auto_reload_augmentation_data_set_lock_key_ttl');
        $dataSetLockKeyPrefix = $container->getParameter('ur.redis.auto_reload_augmentation_data_set_lock_key_prefix');

        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        /** @var Connection $conn */
        $conn = $em->getConnection();
        $this->dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        /** @var LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository */
        $this->linkedMapDataSetRepository = $container->get('ur.repository.linked_map_data_set');

        try {
            $linkedMapDataSets = $this->linkedMapDataSetRepository->findAll();

            $mapDataSetProcessed = [];
            foreach ($linkedMapDataSets as $linkedMapDataSet) {
                if ($linkedMapDataSet instanceof LinkedMapDataSetInterface) {
                    $mapDataSetId = $linkedMapDataSet->getMapDataSet()->getId();
                    if (!in_array($mapDataSetId, $mapDataSetProcessed)) {
                        $this->processAutoReloadForAugmentation($this->redLock, $dataSetLockKeyPrefix, $expiry_time_for_lock, $io, $linkedMapDataSet);
                    }

                    $mapDataSetProcessed[] = $mapDataSetId;
                }
            }
            $io->section(sprintf('Reload all master data set successfully..!'));
        } catch (\Exception $e) {
            $io->warning(sprintf('Errors occurred when trying auto reload Master Data Set on Data Set Map data changed. Detail: %s', $e->getMessage()));
        }
        $conn->close();
    }

    /**
     * process Auto Reload For Augmentation: Reload master data set if map data set's data changed
     *
     * @param RedLock $redLock
     * @param $dataSetLockKeyPrefix
     * @param $expiry_time_for_lock
     * @param $io
     * @param LinkedMapDataSetInterface $linkedMapDataSet
     * @throws \UR\Service\PublicSimpleException
     */
    private function processAutoReloadForAugmentation(RedLock $redLock, $dataSetLockKeyPrefix, $expiry_time_for_lock, SymfonyStyle $io, LinkedMapDataSetInterface $linkedMapDataSet = null)
    {
        if (!$linkedMapDataSet instanceof LinkedMapDataSetInterface) {
            return;
        }

        /** @var DataSetInterface $mapDataSet */
        $mapDataSet = $linkedMapDataSet->getMapDataSet();
        if (!$mapDataSet instanceof DataSetInterface) {
            return;
        }

        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSet($mapDataSet);

        /* we only reload master data set if map data set already up-to-date and map data set's data has changed */
        // for checking data changed
        $currentCheckSum = $this->dataSetSynchronizer->getCheckSumOfDataImportTableByDataSetId($mapDataSet->getId());
        $lastCheckSum = $mapDataSet->getLastCheckSum();

        // for checking up-to-date
        $numChanges = $mapDataSet->getNumChanges();
        $numConnectedDataSource = $mapDataSet->getNumConnectedDataSourceChanges();

        if ($currentCheckSum !== $lastCheckSum
            && ($numChanges == 0 && $numConnectedDataSource == 0)
        ) {
            $startDate = $mapDataSet->getStartDate();
            $endDate = $mapDataSet->getEndDate();

            unset($linkedMapDataSet);
            /* Need reload all master data set of this map data set */
            foreach ($linkedMapDataSets as $linkedMapDataSet) {

                /** @var DataSetInterface $masterDataSet */
                $masterDataSet = $linkedMapDataSet->getConnectedDataSource()->getDataSet();

                if (!$masterDataSet instanceof DataSetInterface || !$masterDataSet->getAutoReload()) {
                    continue;
                }

                // create lock key on redis
                $pid = getmypid();
                $dataSetId = $masterDataSet->getId();
                $lock = $redLock->lock($dataSetLockKeyPrefix . $dataSetId, $expiry_time_for_lock, [
                    'pid' => $pid
                ]);

                if ($lock === false) {
                    $io->section(sprintf('The master Data Set %s: The DataSet is already reloading in another process.', $dataSetId));
                    return;
                }

                /* reload master data set by date range or reload all */
                // TODO: check case either startDate or endDate is null, so which value will be used? ...
                if (!is_null($startDate) || !is_null($endDate)) {
                    $reloadParams = new ReloadParams(
                        ReloadParamsInterface::DETECTED_DATE_RANGE_TYPE,
                        $startDate,
                        $endDate
                    );

                    $this->workerManager->reloadDataSetByDateRange($masterDataSet, $reloadParams);
                } else {
                    $this->workerManager->reloadAllForDataSet($masterDataSet);
                }

            }

            /* do update last checksum for map data set */
            $mapDataSet->setLastCheckSum($currentCheckSum);
            $this->dataSetManager->save($mapDataSet);
        }
    }
}