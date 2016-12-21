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
    const ALERT_CODE_UN_EXPECTED_ERROR = 1000;
    const FILE_NAME = 'fileName';
    const DATA_SOURCE_NAME = 'dataSourceName';
    const FORMAT_FILE = 'formatFile';
    const DATA_SET_NAME = 'dataSetName';
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
        $alert->setDetail([
            self::DETAILS => $details,
            'dataSourceName' => $params[self::DATA_SOURCE_NAME],
            'dataSetName' => $params[self::DATA_SET_NAME],
            'fileName' => $params[self::FILE_NAME]
        ]);
        $this->alertManager->save($alert);
    }

    public function getAlertDetails($alertCode, array $params, &$message, &$details)
    {
        $dataSourceName = $params[self::DATA_SOURCE_NAME];
        $dataSetName = $params[self::DATA_SET_NAME];
        $fileName = $params[self::FILE_NAME];
        switch ($alertCode) {
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD:
                $message = sprintf("File %s has been successfully uploaded to %s", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL:
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API:
                $message = sprintf("File %s has been successfully received to %s", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT:
                $message = sprintf("Failed to Upload %s to %s - Wrong format error", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT:
            case self::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT:
                $message = sprintf("Filed to Receive %s to %s - wrong format error", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::ALERT_CODE_DATA_IMPORTED_SUCCESSFULLY:
                $message = sprintf("Data of file %s in data source %s has been successfully imported to %s", $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL:
                $message = sprintf("Failed to import file %s of %s to %s - mapping error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("no Field in file %s is mapped to dataSet", $fileName);
                break;
            case self::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL:
                $message = sprintf("Failed to import file %s of %s to %s - require error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("Error at column [%s] - Require Fields not exist in file", $params[self::COLUMN]);
                break;
            case self::ALERT_CODE_FILTER_ERROR_INVALID_NUMBER:
                $message = sprintf("Failed to import file %s of %s to %s - filter error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("Wrong number format at row [%s] - column [%s]", $params[self::ROW], $params[self::COLUMN]);
                break;
            case self::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE:
                $message = sprintf("Failed to import file %s of %s to %s - transform error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("Wrong date format at row [%s] - column [%s]", $params[self::ROW], $params[self::COLUMN]);
                break;
            case self::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND:
                $message = sprintf("Failed to import file %s of %s to %s - no header found error", $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = sprintf("Failed to import file %s of %s to %s - can't find data error", $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::ALERT_CODE_UN_EXPECTED_ERROR:
                $message = sprintf("Failed to import file %s of %s to %s - error: %s", $fileName, $dataSourceName, $dataSetName, $params[self::MESSAGE]);
                $details = $message;
                break;
            default:
                break;
        }
    }
}