<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;
use UR\Model\User\Role\PublisherInterface;

class ProcessAlert implements ProcessAlertInterface
{
    const NEW_DATA_IS_RECEIVED_FROM_UPLOAD = 100;
    const NEW_DATA_IS_RECEIVED_FROM_EMAIL = 101;
    const NEW_DATA_IS_RECEIVED_FROM_API = 102;
    const NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT = 103;
    const NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT = 104;
    const NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT = 105;
    const DATA_IMPORTED_SUCCESSFULLY = 200;
    const DATA_IMPORT_MAPPING_FAIL = 201;
    const DATA_IMPORT_REQUIRED_FAIL = 202;
    const FILTER_ERROR_INVALID_NUMBER = 203;
    const TRANSFORM_ERROR_INVALID_DATE = 204;
    const DATA_IMPORT_NO_HEADER_FOUND = 205;
    const DATA_IMPORT_NO_DATA_ROW_FOUND = 206;
    const DATA_SOURCE_MODULE = 1;
    const DATA_SET_MODULE = 2;
    const UNSUPPORTED_MODULE = 0;
    const FILE_NAME = 'fileName';
    const DATA_SOURCE_NAME = 'dataSourceName';
    const FORMAT_FILE = 'formatFile';
    const DATA_SET_NAME = 'dataSetName';
    const ROW = 'row';
    const COLUMN = 'column';
    const MESSAGE = 'message';
    const DETAILS = 'details';
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
        $detailsMessage = $this->getAlertDetails($alertCode, $params, $message, $details);
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setMessage([self::MESSAGE => $detailsMessage[self::MESSAGE]]);
        $alert->setDetail([self::DETAILS => $detailsMessage[self::DETAILS]]);
        $this->alertManager->save($alert);
    }

    public function getAlertDetails($alertCode, array $params, $message, $details)
    {
        $dataSourceName = $params[self::DATA_SOURCE_NAME];
        $dataSetName = $params[self::DATA_SET_NAME];
        $fileName = $params[self::FILE_NAME];
        switch ($alertCode) {
            case self::NEW_DATA_IS_RECEIVED_FROM_UPLOAD:
                $message = "File " . $fileName . " has been successfuly uploaded to " . $dataSourceName;
                break;
            case self::NEW_DATA_IS_RECEIVED_FROM_EMAIL:
            case self::NEW_DATA_IS_RECEIVED_FROM_API:
                $message = "File " . $fileName . " has been successfuly received to " . $dataSourceName;
                break;
            case self::NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT:
                $message = "Failed to Upload " . $fileName . " to " . $dataSourceName . " Wrong format error";
                break;
            case self::NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT:
            case self::NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT:
                $message = "Filed to Receive " . $fileName . " to " . $dataSourceName . " Wrong format error";
                break;
            case self::DATA_IMPORTED_SUCCESSFULLY:
                $message = "Data of File " . $fileName . " has been successfuly Imported to " . $dataSetName;
                break;
            case self::DATA_IMPORT_MAPPING_FAIL:
                $message = "Failed to import File " . $fileName . " to " . $dataSetName . " - Mapping error";
                $details = "no Field in File is mapped to dataSet";
                break;
            case self::DATA_IMPORT_REQUIRED_FAIL:
                $message = "Failed to import File " . $fileName . " to " . $dataSetName . " -  Require error";
                $details = "Error at column [" . $params[self::COLUMN] . "] - Require Fields not exist in File";
                break;
            case self::FILTER_ERROR_INVALID_NUMBER:
                $message = "Failed to import File " . $fileName . " to " . $dataSetName . " -  Filter error";
                $details = "Wrong number format at row " . $params[self::ROW] . " column [" . $params[self::COLUMN] . "]";
                break;
            case self::TRANSFORM_ERROR_INVALID_DATE:
                $message = "Failed to import File " . $fileName . " to " . $dataSetName . " -  Transform error";
                $details = "Wrong date format at row " . $params[self::ROW] . " column [" . $params[self::COLUMN] . "]";
                break;
            case self::DATA_IMPORT_NO_HEADER_FOUND:
                $message = "Failed to import File " . $fileName . " to " . $dataSetName . " -  no Header Found Error";
                break;
            case self::DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = "Failed to import File " . $fileName . " to " . $dataSetName . " -  Can't find Data Error";
                break;
            default:
                break;
        }
        return [self::MESSAGE => $message, self::DETAILS => $details];
    }
}