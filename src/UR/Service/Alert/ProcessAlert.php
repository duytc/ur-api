<?php

namespace UR\Service\Alert;


use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
    const UN_EXPECTED_ERROR = 1000;
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
            case self::NEW_DATA_IS_RECEIVED_FROM_UPLOAD:
                $message = sprintf("File %s has been successfuly uploaded to %s", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::NEW_DATA_IS_RECEIVED_FROM_EMAIL:
            case self::NEW_DATA_IS_RECEIVED_FROM_API:
                $message = sprintf("File %s has been successfuly received to %s", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT:
                $message = sprintf("Failed to Upload %s to %s - Wrong format error", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT:
            case self::NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT:
                $message = sprintf("Filed to Receive %s to %s - Wrong format error", $fileName, $dataSourceName);
                $details = $message;
                break;
            case self::DATA_IMPORTED_SUCCESSFULLY:
                $message = sprintf("Data of File %s in Data Source %s has been successfuly imported to %s", $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::DATA_IMPORT_MAPPING_FAIL:
                $message = sprintf("Failed to import File %s of %s to %s - Mapping error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("no Field in File %s is mapped to dataSet", $fileName);
                break;
            case self::DATA_IMPORT_REQUIRED_FAIL:
                $message = sprintf("Failed to import File %s of %s to %s - Require error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("Error at column [%s] - Require Fields not exist in File", $params[self::COLUMN]);
                break;
            case self::FILTER_ERROR_INVALID_NUMBER:
                $message = sprintf("Failed to import File %s of %s to %s - Filter error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("Wrong number format at row [%s] - column [%s]", $params[self::ROW], $params[self::COLUMN]);
                break;
            case self::TRANSFORM_ERROR_INVALID_DATE:
                $message = sprintf("Failed to import File %s of %s to %s - Transform error", $fileName, $dataSourceName, $dataSetName);
                $details = sprintf("Wrong date format at row [%s] - column [%s]", $params[self::ROW], $params[self::COLUMN]);
                break;
            case self::DATA_IMPORT_NO_HEADER_FOUND:
                $message = sprintf("Failed to import File %s of %s to %s - No Header found Error", $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = sprintf("Failed to import File %s of %s to %s - Can't find Data Error", $fileName, $dataSourceName, $dataSetName);
                $details = $message;
                break;
            case self::UN_EXPECTED_ERROR:
                $message = sprintf("Failed to import File %s of %s to %s - Error: %s", $fileName, $dataSourceName, $dataSetName, $params[self::MESSAGE]);
                $details = $message;
                break;
            default:
                break;
        }
    }
}