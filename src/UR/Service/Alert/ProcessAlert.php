<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\User\Role\PublisherInterface;

class ProcessAlert implements ProcessAlertInterface
{
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD = 100;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL = 101;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API = 102;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT = 103;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT = 104;
    const ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT = 105;
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
    const FILE_NAME = 'fileName';
    const DATA_SOURCE_NAME = 'dataSourceName';
    const DATA_SOURCE_ID = 'dataSourceId';
    const FORMAT_FILE = 'formatFile';
    const DATA_SET_NAME = 'dataSetName';
    const DATA_SET_ID = 'dataSetId';
    const IMPORT_ID = 'importId';
    const ENTRY_ID = 'entryId';
    const ROW = 'row';
    const COLUMN = 'column';
    const MESSAGE = 'message';
    const DETAILS = 'detail';
    const ERROR = 'error';

    protected $alertManager;
    protected $publisherManager;

    public function __construct(AlertManagerInterface $alertManager, PublisherManagerInterface $publisherManager)
    {
        $this->alertManager = $alertManager;
        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function createAlert($alertCode, $publisherId, array $params)
    {
        $publisher = $this->publisherManager->findPublisher($publisherId);
        if (!$publisher instanceof PublisherInterface) {
            throw new \Exception(sprintf('Not found that publisher %s', $publisherId));
        }

        $alert = new Alert();
        $message = "";
        $details = "";
        $this->getAlertDetails($alertCode, $params, $message, $details);
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setMessage([self::MESSAGE => $message]);
        $this->checkParams($params);
        $alert->setDetail([
            self::DETAILS => $details,
            self::DATA_SOURCE_NAME => $params[self::DATA_SOURCE_NAME],
            self::DATA_SET_NAME => $params[self::DATA_SET_NAME],
            self::FILE_NAME => $params[self::FILE_NAME]
        ]);

        $this->alertManager->save($alert);
    }

    public function getAlertDetails($alertCode, array $params, &$message, &$details)
    {
        $dataSourceName = array_key_exists(self::DATA_SOURCE_NAME, $params) ? $params[self::DATA_SOURCE_NAME] : null;
        $dataSetName = array_key_exists(self::DATA_SET_NAME, $params) ? $params[self::DATA_SET_NAME] : null;
        $fileName = array_key_exists(self::FILE_NAME, $params) ? $params[self::FILE_NAME] : null;
        $importErrorMessage = sprintf('Failed to import file %s from data source  "%s" to data set "%s".', $fileName, $dataSourceName, $dataSetName);

        switch ($alertCode) {
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD:
                $message = sprintf('File "%s" has been successfully uploaded to data source "%s".', $fileName, $dataSourceName);
                $details = $message;
                break;

            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL:
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API:
                $message = sprintf('File "%s" has been successfully received to data source "%s".', $fileName, $dataSourceName);
                $details = $message;
                break;

            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT:
                $message = sprintf('Failed to upload file "%s" to data source "%s" - wrong format error.', $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT:
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT:
                $message = sprintf('Failed to receive file "%s" to data source "%s" - wrong format error.', $fileName, $dataSourceName);
                $details = $message;
                break;

            case self::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY:
                $message = sprintf('File "%s" from data source %s has been successfully imported to data set "%s".', $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL:
                $message = sprintf('%s - mapping error: no Field in file is mapped to data set.', $importErrorMessage);
                $details = $message;
                break;

            case self::ALERT_CODE_WRONG_TYPE_MAPPING:
                $message = sprintf('%s - mapping error: invalid type on field "%s".', $importErrorMessage, $params[self::COLUMN]);
                $details = $message;
                break;

            case self::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL:
                $message = sprintf('%s - field "%s" is required but not found in file.', $importErrorMessage, $params[self::COLUMN]);
                $details = $message;
                break;

            case self::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER:
                $message = sprintf('%s - invalid number format on field "%s".', $importErrorMessage, $params[self::COLUMN]);
                $details = $message;
                break;
            case self::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE:
                $message = sprintf('%s - invalid date format on field "%s".', $importErrorMessage, $params[self::COLUMN]);
                $details = $message;
                break;

            case self::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND:
                $message = sprintf('%s - no header found.', $importErrorMessage);
                $details = $message;
                break;

            case self::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = sprintf('%s - no data found.', $importErrorMessage);
                $details = $message;
                break;

            case self::ALERT_CODE_FILE_NOT_FOUND:
                $message = sprintf('%s - error: %s.', $importErrorMessage, ' file does not exist ');
                $details = $message;
                break;

            default:
                break;
        }
    }

    public function checkParams(array &$params)
    {
        $params[self::IMPORT_ID] = array_key_exists(self::IMPORT_ID, $params) ? $params[self::IMPORT_ID] : null;
        $params[self::DATA_SET_ID] = array_key_exists(self::DATA_SET_ID, $params) ? $params[self::DATA_SET_ID] : null;
        $params[self::DATA_SET_NAME] = array_key_exists(self::DATA_SET_NAME, $params) ? $params[self::DATA_SET_NAME] : null;
        $params[self::DATA_SOURCE_ID] = array_key_exists(self::DATA_SOURCE_ID, $params) ? $params[self::DATA_SOURCE_ID] : null;
        $params[self::DATA_SOURCE_NAME] = array_key_exists(self::DATA_SOURCE_NAME, $params) ? $params[self::DATA_SOURCE_NAME] : null;
        $params[self::ENTRY_ID] = array_key_exists(self::ENTRY_ID, $params) ? $params[self::ENTRY_ID] : null;
        $params[self::FILE_NAME] = array_key_exists(self::FILE_NAME, $params) ? $params[self::FILE_NAME] : null;
        $params[self::COLUMN] = array_key_exists(self::COLUMN, $params) ? $params[self::COLUMN] : null;
        $params[self::ROW] = array_key_exists(self::ROW, $params) ? $params[self::ROW] : null;
    }
}