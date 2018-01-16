<?php

namespace UR\Bundle\AppBundle\Command;

use Pubvantage\Worker\Scheduler\DataSetLoadFilesConcurrentJobScheduler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\StringUtilTrait;

class ShowRedisKeysOfDataSetLoadingFileCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    const COMMAND_NAME = 'ur:redis:data-set:load-file-keys:show';
    const ALL_PUBLISHERS = 'all-publishers';
    const PUBLISHER = 'enables-user';
    const DATA_SETS = 'data-sets';

    const LOAD_FILES_COUNT_TEMPLATE = "loading-files-count";

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var  SymfonyStyle */
    private $io;

    /** @var  \Redis */
    private $redis;

    /** @var  string */
    private $lockKeyPrefix;

    /** @var  string */
    private $pendingJobPrefix;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption(self::ALL_PUBLISHERS, 'a', InputOption::VALUE_NONE,
                'For all users')
            ->addOption(self::PUBLISHER, 'u', InputOption::VALUE_OPTIONAL,
                'For special users. Allow multiple users, separated by command. Example 2,15,4')
            ->addOption(self::DATA_SETS, 'r', InputOption::VALUE_OPTIONAL,
                'For special data sets. Allow multiple data sets, separated by comma. Example 15,17,29')
            ->setDescription('Show redis keys for loading file on data set');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $this->redis = $container->get('ur.redis.app_cache');
        $this->lockKeyPrefix = $container->getParameter("ur.worker.lock_key_prefix");
        $this->pendingJobPrefix = $container->getParameter("ur.worker.pending_job_count_key_prefix");

        $this->io = new SymfonyStyle($input, $output);

        $dataSets = $this->getDataSetsFromInput($input);

        if (!is_array($dataSets) || count($dataSets) < 1) {
            $this->io->warning('No data sets found. Please recheck your input. Quit command');
            return;
        }

        foreach ($dataSets as $key => $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $this->io->write(sprintf("(%s/%s) ", $key + 1, count($dataSets)));
            $this->showRedisKeyForDataSet($dataSet);
        }

        $this->io->success(sprintf("Show successfully info for %s data sets. Quit command", count($dataSets)));
    }

    /**
     * @param DataSetInterface $dataSet
     */
    private function showRedisKeyForDataSet(DataSetInterface $dataSet)
    {
        $this->io->write(sprintf("Show keys for data set '%s', id = %s", $dataSet->getName(), $dataSet->getId()));
        $allKeys = $this->getAllLoadFileKeys($dataSet);
        $this->io->section(sprintf("Total keys: %s", count($allKeys)));

        if (count($allKeys) < 1) {
            $this->io->newLine(2);
            return;
        }

        $pendingKeys = $this->filterPendingJobKeys($allKeys);
        $lockKeys = $this->filterLockKeys($allKeys);
        $concurrentKeys = $this->filterConcurrentFileCountKeys($allKeys);

        $this->filterUnClassifiedKeys($allKeys, [$lockKeys, $pendingKeys, $concurrentKeys]);

        $this->io->newLine(2);
    }

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getAllLoadFileKeys(DataSetInterface $dataSet)
    {
        $linearTubeName = DataSetLoadFilesConcurrentJobScheduler::getDataSetTubeName($dataSet->getId());

        return array_unique(array_merge(
            $this->redis->keys(sprintf("*%s-*", $linearTubeName)),
            $this->redis->keys(sprintf("*%s", $linearTubeName))
        ));
    }

    /**
     * @param $allKeys
     * @return mixed
     */
    private function filterLockKeys($allKeys)
    {
        $allKeys = is_array($allKeys) ? $allKeys : [$allKeys];

        $lockKeys = array_filter($allKeys, function ($key) {
            return strpos($key, $this->lockKeyPrefix) !== false;
        });

        $this->io->text(sprintf("Total lock keys: %s", count($lockKeys)));

        $this->showKeysWithValue($lockKeys);

        return $lockKeys;
    }

    /**
     * @param $allKeys
     * @return mixed
     */
    private function filterConcurrentFileCountKeys($allKeys)
    {
        $allKeys = is_array($allKeys) ? $allKeys : [$allKeys];

        $concurrentKeys = array_filter($allKeys, function ($key) {
            return strpos($key, self::LOAD_FILES_COUNT_TEMPLATE) !== false;
        });

        $this->io->text(sprintf("Total concurrent file count keys: %s", count($concurrentKeys)));

        $this->showKeysWithValue($concurrentKeys);

        return $concurrentKeys;
    }

    /**
     * @param $allKeys
     * @return mixed
     */
    private function filterPendingJobKeys($allKeys)
    {
        $allKeys = is_array($allKeys) ? $allKeys : [$allKeys];

        $pendingKeys = array_filter($allKeys, function ($key) {
            return strpos($key, $this->pendingJobPrefix) !== false;
        });

        $this->io->text(sprintf("Total pending job count keys: %s", count($pendingKeys)));

        $this->showKeysWithValue($pendingKeys);

        return $pendingKeys;
    }

    /**
     * @param $allKeys
     * @param $classifiedKeys
     */
    private function filterUnClassifiedKeys($allKeys, $classifiedKeys)
    {
        $classifiedKeys = is_array($classifiedKeys) ? $classifiedKeys : [$classifiedKeys];

        $allClassifiedKeys = [];
        foreach ($classifiedKeys as $keys) {
            if (is_array($keys)) {
                $allClassifiedKeys = array_merge($allClassifiedKeys, $keys);
            } else {
                $allClassifiedKeys[] = $keys;
            }
        }

        $allKeys = is_array($allKeys) ? $allKeys : [$allKeys];
        $unclassifiedKeys = array_diff($allKeys, $allClassifiedKeys);

        $this->io->text(sprintf("Total unclassified keys: %s", count($unclassifiedKeys)));

        $this->showKeysWithValue($unclassifiedKeys);
    }

    /**
     * @param $keys
     */
    private function showKeysWithValue($keys)
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            $value = $this->redis->get($key);
            $this->io->note(sprintf("\tKey:\t%s\n\tValue:\t%s", $key, $value));
        }
    }

    /**
     * @param InputInterface $input
     * @return mixed
     */
    private function getDataSetsFromInput(InputInterface $input)
    {
        $allPublisher = $input->getOption(self::ALL_PUBLISHERS);
        $publisherIds = $input->getOption(self::PUBLISHER);
        $dataSetIds = $input->getOption(self::DATA_SETS);

        if (empty($allPublisher) &&
            empty($publisherIds) &&
            empty($dataSetIds)
        ) {
            return [];
        }

        if (!empty($allPublisher)) {
            return $this->dataSetManager->all();
        }

        if (!empty($publisherIds)) {
            $publisherIds = array_unique(explode(",", $publisherIds));
            $dataSets = [];

            foreach ($publisherIds as $publisherId) {
                $publisher = $this->publisherManager->find($publisherId);
                if (!$publisher instanceof PublisherInterface) {
                    continue;
                }

                $dataSets = array_merge($dataSets, $this->dataSetManager->getDataSetForPublisher($publisher));
            }

            return $dataSets;
        }

        if (!empty($dataSetIds)) {
            $dataSetIds = array_unique(explode(",", $dataSetIds));
            $dataSets = [];

            foreach ($dataSetIds as $dataSetId) {
                $dataSet = $this->dataSetManager->find($dataSetId);

                if (!$dataSet instanceof DataSetInterface) {
                    continue;
                }

                $dataSets[] = $dataSet;
            }

            return $dataSets;
        }

        return [];
    }
}