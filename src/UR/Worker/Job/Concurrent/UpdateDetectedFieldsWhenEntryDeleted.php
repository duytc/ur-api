<?php

namespace UR\Worker\Job\Concurrent;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Monolog\Logger;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobParams;
use Symfony\Component\Filesystem\Filesystem;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportService;

class UpdateDetectedFieldsWhenEntryDeleted implements LockableJobInterface
{
    const JOB_NAME = 'updateDetectedFieldsWhenEntryDeleted';

    const PARAM_KEY_FORMAT = 'format';
    const PARAM_KEY_PATH = 'path';
    const PARAM_KEY_CHUNK_PATHS = 'chunkPaths';
    const PARAM_KEY_DATA_SOURCE_ID = 'dataSourceId';

    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /** @var ImportService */
    private $importService;

    /** @var string */
    private $uploadFileDir;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(Logger $logger, DataSourceManagerInterface $dataSourceManager, ImportService $importService, $uploadFileDir, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->dataSourceManager = $dataSourceManager;
        $this->importService = $importService;
        $this->uploadFileDir = $uploadFileDir;
        $this->em = $em;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function getLockKey(JobParams $params): string
    {
        return sprintf('ur-data-source-%d', $params->getRequiredParam(self::PARAM_KEY_DATA_SOURCE_ID));
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        $format = $params->getRequiredParam(self::PARAM_KEY_FORMAT);
        $path = $params->getRequiredParam(self::PARAM_KEY_PATH);
        $chunkPaths = $params->getRequiredParam(self::PARAM_KEY_CHUNK_PATHS);
        $dataSourceId = $params->getRequiredParam(self::PARAM_KEY_DATA_SOURCE_ID);

        // important: data source entry may be deleted by cascade when data source is deleted
        // so that, make sure the data source id is valid
        try {
            if (!is_integer($dataSourceId)) {
                throw new Exception(sprintf('Data Source %d invalid (may be deleted before)', $dataSourceId));
            }

            /** @var DataSourceInterface $dataSource */
            $dataSource = $this->dataSourceManager->find($dataSourceId);

            if (!$dataSource instanceof DataSourceInterface) {
                throw new Exception(sprintf('Data Source %d not found (may be deleted before)', $dataSourceId));
            }

            $dataSourceFile = $this->importService->getDataSourceFile($format, $path, $dataSource->getSheets());
            if (!$dataSourceFile instanceof \UR\Service\DataSource\DataSourceInterface) {
                return;
            }
            $newFields = $this->importService->getNewFieldsFromFiles($dataSourceFile);
            $detectedFields = $this->importService->detectFieldsForDataSource(
                $newFields,
                $dataSource->getDetectedFields(),
                ImportService::ACTION_DELETE
            );

            $dataSource->setDetectedFields($detectedFields);
            $this->dataSourceManager->save($dataSource);
        } catch (ImportDataException $e) {
            $this->logger->error(sprintf('could not update detected fields when delete entry, Error occurred: %s', $e->getAlertCode()));
        } catch (Exception $exception) {
            $this->logger->error(sprintf('could not update detected fields when delete entry, Error occurred: %s', $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }

        // TODO: separate delete entry file from disk to linear job after this job

        if (file_exists($this->uploadFileDir . $path)) {
            unlink($this->uploadFileDir . $path);
        }

        if (empty($chunkPaths)) {
            $this->logger->info('There is no chunks file to delete');
            return;
        }

        foreach ($chunkPaths as $chunkPath) {
            if (file_exists($this->uploadFileDir . $chunkPath)) {
                $this->removeFileOrFolder($this->uploadFileDir . $chunkPath);
            }
        }
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