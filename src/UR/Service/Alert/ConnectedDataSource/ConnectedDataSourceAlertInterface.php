<?php

namespace UR\Service\Alert\ConnectedDataSource;


interface ConnectedDataSourceAlertInterface
{
    const ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY = 200;
    const ALERT_CODE_DATA_IMPORT_MAPPING_FAIL = 201;
    const ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL = 202;
    const ALERT_CODE_FILTER_ERROR_INVALID_NUMBER = 203;
    const ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE = 204;
    const ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND = 205;
    const ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND = 206;
    const ALERT_CODE_WRONG_TYPE_MAPPING = 207;
    const ALERT_CODE_FILE_NOT_FOUND = 208;
    const ALERT_CODE_UN_EXPECTED_ERROR = 1000;

    const ROW = 'row';
    const COLUMN = 'column';
    const DATA_ADDED = 'dataAdded';
    const IMPORT_FAILURE= 'importFailure';

    public function getAlertMessage();

    public function getAlertDetails();
}