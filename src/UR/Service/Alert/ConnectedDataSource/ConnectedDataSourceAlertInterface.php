<?php

namespace UR\Service\Alert\ConnectedDataSource;


interface ConnectedDataSourceAlertInterface
{
    const ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY = 1200;
    const ALERT_CODE_DATA_IMPORT_MAPPING_FAIL = 1201;
    const ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL = 1202;
    const ALERT_CODE_FILTER_ERROR_INVALID_NUMBER = 1203;
    const ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE = 1204;
    const ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND = 1205;
    const ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND = 1206;
    const ALERT_CODE_WRONG_TYPE_MAPPING = 1207;
    const ALERT_CODE_FILE_NOT_FOUND = 1208;
    const ALERT_CODE_NO_FILE_PREVIEW = 1209;
    const ALERT_CODE_UN_EXPECTED_ERROR = 2000;
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
}