<?php

namespace UR\Service\Alert\ConnectedDataSource;


interface ConnectedDataSourceAlertInterface
{
    const MESSAGE = 'message';
    const DETAILS = 'detail';

    const ROW = 'row';
    const COLUMN = 'column';
    const DATA_ADDED = 'dataAdded';
    const IMPORT_FAILURE= 'importFailure';
    const DATA_SET_ID= 'dataSetId';
    const DATA_SET_NAME= 'dataSetName';
    const IMPORT_ID= 'importId';
    const FILE_NAME= 'fileName';
    const DATA_SOURCE_ID= 'dataSourceId';
    const DATA_SOURCE_NAME= 'dataSourceName';
    const CODE= 'code';
    const CONTENT= 'content';
}