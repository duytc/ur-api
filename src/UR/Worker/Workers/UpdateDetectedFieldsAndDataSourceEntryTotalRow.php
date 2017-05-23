<?php

namespace UR\Worker\Workers;

use Monolog\Logger;
use stdClass;
use Exception;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportService;

class UpdateDetectedFieldsAndDataSourceEntryTotalRow
{
    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /** @var ImportService */
    private $importService;

    /** @var string */
    private $uploadFileDir;

    function __construct(Logger $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, DataSourceManagerInterface $dataSourceManager, ImportService $importService, $uploadFileDir)
    {
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->dataSourceManager = $dataSourceManager;
        $this->importService = $importService;
        $this->uploadFileDir = $uploadFileDir;
    }

    /**
     * @param stdClass $params
     */
    public function updateDetectedFieldsAndTotalRowWhenEntryInserted(stdClass $params)
    {
        $dataSourceEntryId = $params->entryId;

        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        /**@var DataSourceEntryInterface $dataSourceEntry */
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->warning(sprintf('Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            return;
        }

        $dataSource = $dataSourceEntry->getDataSource();

        // update detected fields (update count of detected fields)
        try {
            $dataSourceFile = $this->importService->getDataSourceFile($dataSource->getFormat(), $dataSourceEntry->getPath());

            $newFields = $this->importService->getNewFieldsFromFiles($dataSourceFile);
            $detectedFields = $this->importService->detectFieldsForDataSource($newFields, $dataSource->getDetectedFields(), ImportService::ACTION_UPLOAD);

            $dataSource->setDetectedFields($detectedFields);
            $totalRow = $dataSourceFile->getTotalRows();

            $dataSourceEntry->setDataSource($dataSource);
            $dataSourceEntry->setTotalRow($totalRow);

            $this->dataSourceEntryManager->save($dataSourceEntry);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    public function updateDetectedFieldsWhenEntryDeleted(stdClass $params)
    {
        $format = $params->format;
        $path = $params->path;
        $dataSourceId = $params->dataSourceId;

        /**
         * @var DataSourceInterface $dataSource
         */
        $dataSource = $this->dataSourceManager->find($dataSourceId);

        if (!$dataSource instanceof DataSourceInterface) {
            $this->logger->warning(sprintf('Data Source %d not found (may be deleted before)', $dataSourceId));
            return;
        }

        try {
            $dataSourceFile = $this->importService->getDataSourceFile($format, $path);
            $newFields = $this->importService->getNewFieldsFromFiles($dataSourceFile);
            $detectedFields = $this->importService->detectFieldsForDataSource(
                $newFields,
                $dataSource->getDetectedFields(),
                ImportService::ACTION_DELETE
            );

            $dataSource->setDetectedFields($detectedFields);
            $this->dataSourceManager->save($dataSource);
        } catch (ImportDataException $e) {
            $this->logger->error(sprintf('Error occurred: %s', $e->getAlertCode()));
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }

        if (file_exists($this->uploadFileDir . $path)) {
            unlink($this->uploadFileDir . $path);
        }
    }
}