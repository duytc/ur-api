<?php

namespace UR\Worker\Job\Concurrent;

use Exception;
use Monolog\Logger;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportService;

class UpdateDetectedFieldsWhenEntryInserted implements JobInterface
{
    const JOB_NAME = 'updateDetectedFieldsWhenEntryInserted';

    const PARAM_KEY_ENTRY_ID = 'entryId';

    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /** @var ImportService */
    private $importService;

    /** @var string */
    private $uploadFileDir;

    public function __construct(Logger $logger, DataSourceManagerInterface $dataSourceManager, DataSourceEntryManagerInterface $dataSourceEntryManager, ImportService $importService, $uploadFileDir)
    {
        $this->logger = $logger;
        $this->dataSourceManager = $dataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importService = $importService;
        $this->uploadFileDir = $uploadFileDir;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        // TODO: do not hardcode, use const instead
        $dataSourceEntryId = (int)$params->getRequiredParam(self::PARAM_KEY_ENTRY_ID);

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->warning(sprintf('Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            return;
        }

        $dataSourceId = $dataSourceEntry->getDataSource()->getId();
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->dataSourceManager->find($dataSourceId);
        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        // update detected fields (update count of detected fields)
        try {
            $dataSourceFile = $this->importService->getDataSourceFile($dataSource->getFormat(), $dataSourceEntry->getPath());

            $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($dataSourceEntry->getFileExtension());
            if ($dataSourceTypeExtension === $dataSource->getFormat()) {
                $newFields = $this->importService->getNewFieldsFromFiles($dataSourceFile);
                $detectedFields = $this->importService->detectFieldsForDataSource($newFields, $dataSource->getDetectedFields(), ImportService::ACTION_UPLOAD);

                $dataSource->setDetectedFields($detectedFields);
                $this->dataSourceManager->save($dataSource);
            } else {
                $this->logger->error(sprintf('Data Source Entry format %s and Data Source format %s not match => skip update detected fields', $dataSourceEntry->getFileExtension(), $dataSource->getFormat()));
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}