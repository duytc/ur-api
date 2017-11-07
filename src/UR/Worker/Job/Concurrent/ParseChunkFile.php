<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\RedLock;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Redis;
use Symfony\Component\Filesystem\Filesystem;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertFactory;
use UR\Service\DataSource\MergeFiles;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Import\ImportDataException;
use UR\Worker\Job\Linear\LoadFileIntoDataSetSubJob;
use UR\Worker\Manager;

class ParseChunkFile implements JobInterface
{
    const DATA_SOURCE_ENTRY_ID = 'data_source_entry_id';
    const IMPORT_HISTORY_ID = 'import_history_id';
    const JOB_NAME = 'parse_chunk_file';
    const INPUT_FILE_PATH = 'input_file_path';
    const OUTPUT_FILE_PATH = 'output_file_path';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const TOTAL_CHUNK_KEY = 'parse_chunk_file:total';
    const CHUNKS_KEY = 'parse_chunk_file:chunks';
    const PROCESS_ALERT_KEY_TEMPLATE = 'data_source_entry_%d_alert';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var Manager */
    private $manager;

    /** @var RedLock */
    private $redLock;

    /** @var Redis */
    private $redis;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var DataSourceEntryManagerInterface */
    private $connectedDataSourceManager;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    /** @var AutoImportDataInterface */
    private $autoImportData;
    private $tempFileDirectory;
    private $uploadFileDirectory;

    public function __construct(LoggerInterface $logger, Manager $manager, Redis $redis, DataSourceEntryManagerInterface $dataSourceEntryManager,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager, AutoImportDataInterface $autoImportData, ImportHistoryManagerInterface $importHistoryManager, $tempFileDirectory, $uploadFileDirectory)
    {
        $this->logger = $logger;
        $this->manager = $manager;
        $this->redis = $redis;
        $this->redLock = new RedLock([$redis]);
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->autoImportData = $autoImportData;
        $this->importHistoryManager = $importHistoryManager;
        $this->tempFileDirectory = $tempFileDirectory;
        $this->uploadFileDirectory = $uploadFileDirectory;

    }

    public function getName(): string
    {
        return static::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $connectedDataSourceId = $params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $dataSourceEntryId = $params->getRequiredParam(self::DATA_SOURCE_ENTRY_ID);

        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            $this->logger->error(sprintf('ConnectedDataSource %d not found or you do not have permission', $connectedDataSourceId));
            return;
        }

        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->error(sprintf('DataSourceEntry %d not found or you do not have permission', $dataSourceEntryId));
            return;
        }

        $importHistoryId = $params->getRequiredParam(self::IMPORT_HISTORY_ID);
        $importHistory = $this->importHistoryManager->find($importHistoryId);
        if (!$importHistory instanceof ImportHistoryInterface) {
            $this->logger->error(sprintf('ImportHistory %d not found or you do not have permission', $importHistoryId));
            return;
        }

        // if one failed, all failed
        $chunkFailedKey = sprintf(LoadFileIntoDataSetSubJob::CHUNK_FAILED_KEY_TEMPLATE, $importHistoryId);
        $failed = $this->redis->exists($chunkFailedKey);
        if ($failed == true) {
            $this->redis->decr($params->getRequiredParam(self::TOTAL_CHUNK_KEY));
            return;
        }

        $inputFile = $params->getRequiredParam(self::INPUT_FILE_PATH);
        $outputFile = $params->getRequiredParam(self::OUTPUT_FILE_PATH);

        $transforms = $connectedDataSource->getTransforms();
        $hasGroup = false;
        foreach ($transforms as $transform) {
            if (is_array($transform) && array_key_exists('type', $transform) && in_array($transform['type'], ['groupBy', 'subsetGroup'])) {
                $hasGroup = true;
                break;
            }
        }

        try {
            if (!$hasGroup) {
                $this->autoImportData->parseFileThenInsert($connectedDataSource, $dataSourceEntry, $importHistory, $inputFile);
            } else {
                $directory = pathinfo($outputFile, PATHINFO_DIRNAME);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                $this->autoImportData->parseFileOnPreGroups($connectedDataSource, $dataSourceEntry, $importHistory, $inputFile, $outputFile);
            }
        } catch (ImportDataException $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            $errorCode = $ex->getAlertCode();
            $failureAlert = $connectedDataSourceAlertFactory->getAlert(
                $importHistory->getId(),
                $connectedDataSource->getAlertSetting(),
                AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                $ex->getColumn(),
                $ex->getRow(),
                $ex->getContent()
            );

            $this->redis->set($chunkFailedKey, 1);
            $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

            $alertProcessed = $this->redis->exists(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId));
            if ($failureAlert != null && $alertProcessed == false) {
                $this->manager->processAlert($errorCode, $connectedDataSource->getDataSource()->getPublisherId(), $failureAlert->getDetails(), $failureAlert->getDataSourceId());
                $this->redis->set(sprintf(self::PROCESS_ALERT_KEY_TEMPLATE, $dataSourceEntryId), 1);
            }

            return;
        }

        $this->redis->decr($params->getRequiredParam(self::TOTAL_CHUNK_KEY));
        $totalChunk = (int)$this->redis->get($params->getRequiredParam(self::TOTAL_CHUNK_KEY));

        //all chunk parsed
        if ($totalChunk <= 0) {
            if ($hasGroup) {
                $this->logger->notice('All chunks parsed completely, merging result');
                $chunks = $this->redis->lRange($params->getRequiredParam(self::CHUNKS_KEY), 0, -1);
                $outputFilePath = $this->getMergedFileDirectory($dataSourceEntry);
                $mergedFileObj = new MergeFiles($chunks, $outputFilePath, $importHistory->getId());
                $mergedFile = $mergedFileObj->mergeFiles();
                $this->autoImportData->parseFileOnPostGroups($connectedDataSource, $dataSourceEntry, $importHistory, $mergedFile);

                // Delete temp file
                $this->logger->notice('Delete temp file');

                foreach ($chunks as $chunk) {
                    if (file_exists($chunk)) {
                        $this->logger->notice('delete temp chunk fle' . $chunk);
                        $this->removeFileOrFolder($chunk);
                    }
                }

                if (file_exists($mergedFile)) {
                    $this->logger->notice('delete temp fle' . $mergedFile);
                    $this->removeFileOrFolder($mergedFile);
                }
            }

            $dataSetId = $connectedDataSource->getDataSet()->getId();
            $this->manager->updateOverwriteDateForDataSet($dataSetId);
            $this->manager->updateTotalRowsForDataSet($dataSetId);
            $this->manager->updateAllConnectedDataSourceTotalRowForDataSet($dataSetId);

            $this->redis->del($params->getRequiredParam(self::TOTAL_CHUNK_KEY));
            $this->redis->del($params->getRequiredParam(self::CHUNKS_KEY));

            $connectedDataSourceAlertFactory = new ConnectedDataSourceAlertFactory();
            /* alert when successful*/
            $importSuccessAlert = $connectedDataSourceAlertFactory->getAlert(
                $importHistory->getId(),
                $connectedDataSource->getAlertSetting(),
                AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY,
                $dataSourceEntry->getFileName(),
                $connectedDataSource->getDataSource(),
                $connectedDataSource->getDataSet(),
                null,
                null,
                null
            );

            $publisherId = $connectedDataSource->getDataSet()->getPublisherId();
            if ($importSuccessAlert !== null) {
                $this->manager->processAlert($importSuccessAlert->getAlertCode(), $publisherId, $importSuccessAlert->getDetails(), $importSuccessAlert->getDataSourceId());
            }
        }
    }

    /**
     * Get directory name  of chunks file
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed
     */
    private function getMergedFileDirectory(DataSourceEntryInterface $dataSourceEntry)
    {
        $chunks = $dataSourceEntry->getChunks();

        $this->logger->info(sprintf('Chunk path of merged file: %s', $chunks[0]));
        $this->logger->info(sprintf('Upload file dir: %s', $this->uploadFileDirectory));
        $firstSourceFileFullPath = sprintf('%s%s', $this->uploadFileDirectory, $chunks[0]);

        $this->logger->info(sprintf('Full path of merged file: %s', $firstSourceFileFullPath));
        return pathinfo($firstSourceFileFullPath, PATHINFO_DIRNAME);
    }

    /**
     * @param $relativeFilePaths
     * @return array|bool|string
     */
    private function getChunkFileFullPaths($relativeFilePaths)
    {
        if (empty($relativeFilePaths) || !is_array($relativeFilePaths)) {
            return false;
        }

        $fullPaths = [];
        foreach ($relativeFilePaths as $relativeFilePath) {
            $fullPaths = sprintf('%s%s', $this->tempFileDirectory, $relativeFilePath);
            $this->logger->info(sprintf('Full path of a file: %s', $fullPaths));
        }

        return $fullPaths;
    }

    private function removeFileOrFolder($path)
    {
        if (!is_file($path) && !is_dir($path)) {
            return;
        }

        $fs = new Filesystem();

        try {
            $fs->remove($path);
        } catch (\Exception $e) {
            $this->logger->notice($e);
        }
    }
}