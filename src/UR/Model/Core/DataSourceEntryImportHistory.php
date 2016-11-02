<?php

namespace UR\Model\Core;

class DataSourceEntryImportHistory implements DataSourceEntryImportHistoryInterface
{
    protected $id;
    protected $status;
    protected $importedDate;
    protected $description;

    /**
     * @var ImportHistoryInterface
     */
    protected $importHistory;

    /**
     * @var DataSourceEntryInterface
     */
    protected $dataSourceEntry;

    public function __construct()
    {

    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @inheritdoc
     */
    public function getImportedDate()
    {
        return $this->importedDate;
    }

    /**
     * @inheritdoc
     */
    public function setImportedDate($importedDate)
    {
        $this->importedDate = $importedDate;
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @inheritdoc
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @inheritdoc
     */
    public function getImportHistory()
    {
        return $this->importHistory;
    }

    /**
     * @inheritdoc
     */
    public function setImportHistory($importHistory)
    {
        $this->importHistory = $importHistory;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntry()
    {
        return $this->dataSourceEntry;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceEntry($dataSourceEntry)
    {
        $this->dataSourceEntry = $dataSourceEntry;
    }
}