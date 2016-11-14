<?php


namespace UR\Service\Alert;


final class AlertParams
{
    const CODE = 'code';
    const FILE_NAME = 'fileName';
    const DATA_SOURCE_NAME = 'dataSourceName';
    const DATA_SET_NAME = 'dataSetName';
    const ROW = 'row';
    const COLUMN = 'column';
    const DATA_SOURCE_ENTRY_NAME = 'dataSourceEntryName';
    const DATA_SOURCE_ENTRY = 'dataSourceEntry';
    const ERROR = 'error';
    const METHOD = 'method';
    const CONNECTED_DATA_SOURCE = 'connectedDataSource';
    const STATUS = 'status';
    const PUBLISHER = 'publisher';

    //UPLOAD
    const UPLOAD_DATA_SUCCESS = 10;
    const UPLOAD_DATA_FAILURE = 11;
    const UPLOAD_DATA_WARNING = 12;

    //IMPORT
    const IMPORT_DATA_SUCCESS = 20;
    const IMPORT_DATA_FAILURE = 21;

    //IMPORT FAILURE
    const REQUIRE_FAIL_IMPORT = 0;
    const FILTER_FAIL_IMPORT = 1;
    const TRANSFORM_FAIL_IMPORT = 2;
    const UNKNOWN_FAIL_IMPORT = 3;

    //UPLOAD FAILURE
    const FAIL_UPLOAD = 0;
}