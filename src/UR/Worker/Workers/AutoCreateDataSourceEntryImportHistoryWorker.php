<?php

namespace UR\Worker\Workers;


use UR\DomainManager\DataSourceEntryImportHistoryManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\DataSourceEntryImportHistory;
use StdClass;
use UR\Service\Parser\History\HistoryType;

class AutoCreateDataSourceEntryImportHistoryWorker
{

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /**
     * @var DataSourceEntryImportHistoryManagerInterface
     */
    private $dataSourceEntryImportHistoryManager;

    public function __construct(DataSourceEntryImportHistoryManagerInterface $dataSourceEntryImportHistoryManager, DataSourceEntryManagerInterface $dataSourceEntryManager, ImportHistoryManagerInterface $importHistoryManager)
    {
        $this->dataSourceEntryImportHistoryManager = $dataSourceEntryImportHistoryManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
    }

    public function createImportHistoryWhenConnectedDataSourceChange(StdClass $params)
    {
        $dseImportHistoryEntity = new DataSourceEntryImportHistory();
        $errors = (array)$params->errors;

        switch ($errors[HistoryType::ERROR_CODE]) {
            case 0:
                $status = "success";
                $desc = "import success file";
                break;
            case 1:// when mapping fail
                $status = "failure";
                $desc = "error when mapping require fields";
                break;
            case 2:
                $status = "failure";
                $desc = "error when Filter file at row " . $errors[HistoryType::ROW] . " column " . $errors[HistoryType::ROW];
                break;
            case 3:
                $status = "failure";
                $desc = "error when Transform file at row " . $errors[HistoryType::ROW] . " column " . $errors[HistoryType::ROW];
                break;
            default:
                $status = "failure";
                $desc = "unknow error";
                break;
        }

        $importHistoryEntity = $this->importHistoryManager->find($errors[HistoryType::IMPORT_HISTORY_ENTITY]);
        $dataSourceEntry= $this->dataSourceEntryManager->find($errors[HistoryType::DATA_SOURCE_ENTRY_ENTITY]);
        $dseImportHistoryEntity->setDataSourceEntry($dataSourceEntry);
        $dseImportHistoryEntity->setImportHistory($importHistoryEntity);
        $dseImportHistoryEntity->setStatus($status);
        $dseImportHistoryEntity->setDescription($desc);
        $this->dataSourceEntryImportHistoryManager->save($dseImportHistoryEntity);
    }

}