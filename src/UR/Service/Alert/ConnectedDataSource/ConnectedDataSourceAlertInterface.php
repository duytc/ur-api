<?php

namespace UR\Service\Alert\ConnectedDataSource;


use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\AlertDTOInterface;

interface ConnectedDataSourceAlertInterface extends AlertDTOInterface
{
    const KEY_ROW = 'row';
    const KEY_COLUMN = 'column';

    const KEY_DATA_SET_ID = 'dataSetId';
    const KEY_DATA_SET_NAME = 'dataSetName';
    const KEY_IMPORT_ID = 'importId';
    const KEY_CONTENT = 'content';

    const TYPE_DATA_ADDED = 'dataAdded';
    const TYPE_IMPORT_FAILURE = 'importFailure';

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