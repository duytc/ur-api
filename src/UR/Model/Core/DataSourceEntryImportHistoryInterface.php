<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceEntryImportHistoryInterface extends ModelInterface
{
    /**
     * @inheritdoc
     */
    public function getId();

    /**
     * @return mixed
     */
    public function getStatus();

    /**
     * @param mixed $status
     */
    public function setStatus($status);

    /**
     * @return mixed
     */
    public function getImportedDate();

    /**
     * @param mixed $importedDate
     */
    public function setImportedDate($importedDate);

    /**
     * @return mixed
     */
    public function getDescription();

    /**
     * @param mixed $description
     */
    public function setDescription($description);

    /**
     * @return ImportHistoryInterface
     */
    public function getImportHistory();

    /**
     * @param ImportHistoryInterface $importHistory
     */
    public function setImportHistory($importHistory);

    /**
     * @return DataSourceEntryInterface
     */
    public function getDataSourceEntry();

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    public function setDataSourceEntry($dataSourceEntry);
}