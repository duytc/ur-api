<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\User\Role\PublisherInterface;

class RemoveDataSourceEntryCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:data-source:remove-entry';
    const ID = 'id';
    const DRY_RUN = 'dry-run';
    const DATA_SOURCE_ID = 'data-source-id';
    const PUBLISHER = 'publisher';
    const ONLY_404 = 'only-404';

    /** @var Logger */
    private $logger;

    /** @var  DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var  DataSourceManagerInterface */
    private $dataSourceManager;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var ImportHistoryManagerInterface $importHistoryManager */
    private $importHistoryManager;

    /** @var  string */
    private $uploadFileDir;

    /** @var EntityManagerInterface */
    private $em;

    /** @var  ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Command remove data source entry entity (and file if it exists), import history entity')
            ->addOption(self::ID, 'i', InputOption::VALUE_OPTIONAL,
                'Id of dataSource entry (optional)')
            ->addOption(self::DATA_SOURCE_ID, 'd', InputOption::VALUE_OPTIONAL,
                'Id of dataSource (optional)')
            ->addOption(self::PUBLISHER, 'p', InputOption::VALUE_OPTIONAL,
                'Id of publisher (optional)')
            ->addOption(self::ONLY_404, 'o', InputOption::VALUE_NONE,
                'Only use entries with missing file path, otherwise all entries in data source (optional)')
            ->addOption(self::DRY_RUN, 'r', InputOption::VALUE_NONE,
                'Do not execute changes, display them only (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');
        $this->dataSourceEntryManager = $container->get('ur.domain_manager.data_source_entry');
        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $this->importHistoryManager = $container->get('ur.domain_manager.import_history');
        $this->uploadFileDir = $container->getParameter('upload_file_dir');
        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');

        if (!$this->isValidInput($input, $output)) {
            return;
        }
        $output->writeln('Starting command...');

        $dataSourceEntries = $this->getDataSourceEntries($input);

        if ($input->getOption(self::ONLY_404)) {
            $dataSourceEntries = $this->filterDataSourceEntriesMissingFilePath($dataSourceEntries);
        }

        if (empty($dataSourceEntries)) {
            $output->writeln('None dataSource entries match your input, please check again.');
            $output->writeln('Quit command');
            return;
        }

        if ($input->getOption(self::DRY_RUN)) {
            $this->displayDataSourceEntriesOnly($dataSourceEntries, $output);
            return;
        }

        $connectedDataSources = [];
        foreach ($dataSourceEntries as $dataSourceEntry) {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                continue;
            }

            $this->unloadDataSourceEntry($dataSourceEntry);

            $this->removeEntryFile($dataSourceEntry);

            $dataSource = $dataSourceEntry->getDataSource();
            $connected = $this->connectedDataSourceManager->getConnectedDataSourceByDataSource($dataSource);
            if (is_array($connected)) {
                $connectedDataSources = array_merge($connectedDataSources, $connected);
            }

            $output->writeln('Delete success dataSource entry ' . $dataSourceEntry->getId());
            $this->dataSourceEntryManager->delete($dataSourceEntry);
        }

        $output->writeln('Command run successfully...');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function isValidInput($input, $output)
    {
        $output->writeln('Validate input...');

        $count = 0;
        if ($input->getOption(self::ID)) {
            $count++;
        }
        if ($input->getOption(self::DATA_SOURCE_ID)) {
            $count++;
        }
        if ($input->getOption(self::PUBLISHER)) {
            $count++;
        }

        if ($count < 1) {
            $message = sprintf('Missing input, please try option %s, or %s, or $s', self::ID, self::DATA_SOURCE_ID, self::PUBLISHER);
            $output->writeln($message);
            return false;
        }

        if ($count > 1) {
            $message = sprintf('Do not use option %s, %s, %s together, please try one of them', self::ID, self::DATA_SOURCE_ID, self::PUBLISHER);
            $output->writeln($message);
            return false;
        }

        if ($input->getOption(self::ID)) {
            $dataSourceEntryId = $input->getOption(self::ID);
            $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);

            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                $output->writeln('Can not find dataSource entry with id ' . $dataSourceEntryId);
                return false;
            }
        }

        if ($input->getOption(self::DATA_SOURCE_ID)) {
            $dataSourceId = $input->getOption(self::DATA_SOURCE_ID);
            $dataSource = $this->dataSourceManager->find($dataSourceId);

            if (!$dataSource instanceof DataSourceInterface) {
                $output->writeln('Can not find dataSource with id ' . $dataSourceId);
                return false;
            }
        }

        if ($input->getOption(self::PUBLISHER)) {
            $publisherId = $input->getOption(self::PUBLISHER);
            $publisher = $this->publisherManager->findPublisher($publisherId);

            if (!$publisher instanceof PublisherInterface) {
                $output->writeln('Can not find publisher with id ' . $publisherId);
                return false;
            }
        }

        return true;
    }

    /**
     * @param InputInterface $input
     * @return \UR\Model\Core\DataSourceEntryInterface[]
     */
    private function getDataSourceEntries($input)
    {
        $dataSourceEntries = [];
        if ($input->getOption(self::ID)) {
            $dataSourceEntries[] = $this->dataSourceEntryManager->find($input->getOption(self::ID));
        } elseif ($input->getOption(self::DATA_SOURCE_ID)) {
            /** @var DataSourceInterface $dataSource */
            $dataSource = $this->dataSourceManager->find($input->getOption(self::DATA_SOURCE_ID));
            $dataSourceEntries = $this->dataSourceEntryManager->findByDataSource($dataSource);
        } elseif ($input->getOption(self::PUBLISHER)) {
            $publisher = $this->publisherManager->findPublisher($input->getOption(self::PUBLISHER));
            $dataSourceEntries = $this->dataSourceEntryManager->getDataSourceEntryForPublisher($publisher);
        }
        return $dataSourceEntries;
    }

    /**
     * @param $dataSourceEntries
     * @return DataSourceEntryInterface[]
     */
    private function filterDataSourceEntriesMissingFilePath($dataSourceEntries)
    {
        $dataSourceEntries = array_filter($dataSourceEntries, function ($dataSourceEntry) {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                return false;
            }
            $filePath = $this->getFilePath($dataSourceEntry);
            if (!is_file($filePath)) {
                return true;
            }
            return false;
        });
        return array_values($dataSourceEntries);
    }

    /**
     * @param DataSourceEntryInterface[] $dataSourceEntries
     * @param OutputInterface $output
     */
    private function displayDataSourceEntriesOnly($dataSourceEntries, $output)
    {
        $output->writeln('Display dataSource entries, not delete anything');

        foreach ($dataSourceEntries as $dataSourceEntry) {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                continue;
            }
            $message = sprintf('Entry %s, with path %s', $dataSourceEntry->getId(), $dataSourceEntry->getPath());
            $output->writeln($message);
        }

        $output->writeln('If you want to delete dataSource entries, please rerun command without option ' . self::DRY_RUN);
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function unloadDataSourceEntry($dataSourceEntry)
    {
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $importHistories = $this->importHistoryManager->getImportHistoryByDataSourceEntryWithoutDataSet($dataSourceEntry);

        foreach ($importHistories as $importHistory) {
            if (!$importHistory instanceof ImportHistoryInterface) {
                continue;
            }

            $this->logger->info(sprintf('Delete import history %s on dataSource entry %s',
                $importHistory->getId(),
                $dataSourceEntry->getId()));
            $this->importHistoryManager->deleteImportedData([$importHistory]);
            $this->importHistoryManager->delete($importHistory);
        }
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function removeEntryFile($dataSourceEntry)
    {
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $filePath = $this->getFilePath($dataSourceEntry);
        if (is_file($filePath)) {
            unlink($filePath);
            $this->logger->info('Delete file ' . $filePath . ' on dataSource entry ' . $dataSourceEntry->getId());
        }
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return string
     */
    private function getFilePath($dataSourceEntry)
    {
        $filePath = $dataSourceEntry->getPath();
        $realFilePath = sprintf('%s%s', $this->uploadFileDir, $filePath);
        return $realFilePath;
    }
}