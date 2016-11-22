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
    const NEW_DATA_IS_ADD_TO_CONNECTED_DATA_SOURCE = 200;
    const DATA_IMPORT_MAPPING_FAIL = 201;
    const DATA_IMPORT_REQUIRED_FAIL = 202;
    const DATA_IMPORT_FILTER_FAIL = 203;
    const DATA_IMPORT_TRANSFORM_FAIL = 204;

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
    const ERROR = 'error';

    /**
     * Status codes translation table.
     *
     * @var array
     */
    public static $alertCodes = array(
        ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_UPLOAD => 'New data is received from Upload',      // error codes for dataSource
        ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_EMAIL => 'New data is received from Email',
        ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_API => 'New data is received from API',
        ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT => 'New data is received from Upload in wrong format',
        ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_EMAIL_WRONG_FORMAT => 'New data is received from Email in wrong format',
        ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_API_WRONG_FORMAT => 'New data is received API in wrong format',
        ProcessAlert::NEW_DATA_IS_ADD_TO_CONNECTED_DATA_SOURCE => 'New data is add to the connected data source',              // error codes for dataSet
        ProcessAlert::DATA_IMPORT_REQUIRED_FAIL => 'Data import required fail',
        ProcessAlert::DATA_IMPORT_FILTER_FAIL => 'Data import filter fail',
        ProcessAlert::DATA_IMPORT_TRANSFORM_FAIL => 'Data import transform fail'
    );

    public static $datSourceErrorParams = array(
        ProcessAlert::FILE_NAME => 'require',
        ProcessAlert::DATA_SOURCE_NAME => 'option'
    );

    public static $datSetErrorParams = array(
        ProcessAlert::DATA_SET_NAME => 'require',
        ProcessAlert::DATA_SOURCE_NAME => 'require',
        ProcessAlert::FILE_NAME => 'require',
        ProcessAlert::ROW => 'option',
        ProcessAlert::COLUMN => 'option'
    );

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
        if (!array_key_exists($alertCode, self::$alertCodes)) {
            throw new \Exception(sprintf('Alert code %d is not valid', $alertCode));
        }

        $publisher = $this->publisherManager->findPublisher($publisherId);
        if (!$publisher instanceof PublisherInterface) {
            throw new \Exception(sprintf('Not found that publisher %s', $publisherId));
        }

        $this->validateParams($alertCode, $params);

        $alert = new Alert();
        $alert->setCode($alertCode);
        $alert->setPublisher($publisher);
        $alert->setMessage($params);
        $this->alertManager->save($alert);
    }

    /**
     * @param $alertCode
     * @param $params
     * @throws \Exception
     */
    protected function validateParams($alertCode, $params)
    {
        $caller = $this->getCallerFromAlertCode($alertCode);

        switch ($caller) {
            case self::DATA_SOURCE_MODULE:
                foreach (self::$datSourceErrorParams as $dataSourceErrorParam => $value) {
                    if ($value == 'require') {
                        if (!array_key_exists($dataSourceErrorParam, $params)) {
                            throw new \Exception (sprintf('Alert code %d need %d param', $alertCode, $dataSourceErrorParam));
                        }
                    }
                }
                break;
            case self::DATA_SET_MODULE:
                foreach (self::$datSetErrorParams as $dataSetErrorParam => $value) {
                    if ($value == 'require') {
                        if (!array_key_exists($dataSetErrorParam, $params)) {
                            throw new \Exception (sprintf('Alert code %d need %d param', $alertCode, $dataSetErrorParam));
                        }
                    }
                }
                break;
            default:
                throw new \Exception (sprintf('System not support error code %d', $alertCode));
                break;
        }
    }

    /**
     * @param $alertCode
     * @return int
     */
    protected function getCallerFromAlertCode($alertCode)
    {
        $startWithNumber = substr($alertCode, 0, 1);

        switch ($startWithNumber) {
            case 1:
                return self::DATA_SOURCE_MODULE;
                break;
            case 2:
                return self::DATA_SET_MODULE;
                break;
            default:
                return self::UNSUPPORTED_MODULE;
                break;
        }
    }
}