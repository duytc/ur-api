<?php

namespace UR\Worker\Job\Concurrent;

use Exception;
use Monolog\Logger;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\DataSource\DataSourceAlertInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportService;
use UR\Worker\Manager;

class UpdateDetectedFieldsWhenEntryInserted implements LockableJobInterface
{
    const JOB_NAME = 'updateDetectedFieldsWhenEntryInserted';

    const PARAM_KEY_ENTRY_ID = 'entryId';
    const PARAM_KEY_DATA_SOURCE_ID = 'data_source_id';

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

    /**
     * @var Manager
     */
    private $manager;

    public function __construct(Logger $logger, DataSourceManagerInterface $dataSourceManager, DataSourceEntryManagerInterface $dataSourceEntryManager,
                                ImportService $importService, $uploadFileDir, $manager)
    {
        $this->logger = $logger;
        $this->dataSourceManager = $dataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importService = $importService;
        $this->uploadFileDir = $uploadFileDir;
        $this->manager = $manager;
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
        $dataSourceEntryId = (int)$params->getRequiredParam(self::PARAM_KEY_ENTRY_ID);

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface || !$dataSourceEntry->getDataSource() instanceof DataSourceInterface) {
            $this->logger->warning(sprintf('Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            return;
        }

        $dataSource = $dataSourceEntry->getDataSource();
        $dataSourceId = $dataSource->getId();

        // update detected fields (update count of detected fields)
        try {
            $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($dataSourceEntry->getFileExtension());
            $dataSourceFile = $this->importService->getDataSourceFile($dataSourceTypeExtension, $dataSourceEntry->getPath());

            $newFields = $this->importService->getNewFieldsFromFiles($dataSourceFile);
            $detectedFields = $this->importService->detectFieldsForDataSource($newFields, $dataSource->getDetectedFields(), ImportService::ACTION_UPLOAD);

            $dataSource->setDetectedFields($detectedFields);
            $this->dataSourceManager->save($dataSource);
        } catch (Exception $exception) {
            $this->manager->processAlert(
                AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT,
                $dataSource->getPublisherId(),
                [
                    DataSourceAlertInterface::DATA_SOURCE_ID => $dataSourceId,
                    DataSourceAlertInterface::DATA_SOURCE_NAME => $dataSource->getName(),
                    DataSourceAlertInterface::FILE_NAME => $dataSourceEntry->getPath()
                ],
                $dataSourceId
            );
            $this->logger->error($exception->getMessage());
        }
    }
}