<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\AlertDTOInterface;

interface ConnectedDataSourceAlertInterface extends AlertDTOInterface
{
    const ROW = 'row';
    const COLUMN = 'column';
    const DATA_ADDED = 'dataAdded';
    const IMPORT_FAILURE = 'importFailure';
    const DATA_SET_ID = 'dataSetId';
    const DATA_SET_NAME = 'dataSetName';
    const IMPORT_ID = 'importId';
    const CONTENT = 'content';

    /**
     * @return mixed
     */
    public function getDetails();

    /**
     * @return int
     */
    public function getAlertCode();

    /**
     * @return string
     */
    public function getFileName();

    /**
     * @return null|DataSourceInterface
     */
    public function getDataSource();

    /**
     * @return null|int
     */
    public function getDataSourceId();

    /**
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @return int
     */
    public function getImportId();
}