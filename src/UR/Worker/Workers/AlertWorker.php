<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\Model\User\UserEntityInterface;
use UR\Service\Alert\AlertParams;
use UR\Service\Alert\ProcessAlertInterface;

class AlertWorker implements AlertWorkerInterface
{
    /**
     * @var UserEntityInterface
     */
    private $alert;

    /**
     * AlertWorker constructor.
     * @param ProcessAlertInterface $alert
     */
    public function __construct(ProcessAlertInterface $alert)
    {
        $this->alert = $alert;
    }


    /**
     * @param StdClass $params
     * @throws \Exception
     */
    public function processAlert(StdClass $params)
    {
        $params = (array) $params->parameters;
        if ($params[AlertParams::CODE] == null || ($params == null || !is_array($params))) {
            throw new \Exception('Expect code in $params and params must be an array');
        }

        $code = $params[AlertParams::CODE];

        switch ($code) {
            case AlertParams::UPLOAD_DATA_SUCCESS:
                if (!array_key_exists(AlertParams::DATA_SOURCE_ENTRY, $params)) {
                    throw new \Exception('Expect data source entry for upload data success');
                }
                $this->alert->uploadAlert($params);
                break;
            case AlertParams::UPLOAD_DATA_FAILURE:
                if (!array_key_exists(AlertParams::PUBLISHER, $params)) {
                    throw new \Exception('Expect publisher for upload data success');
                }
                $this->alert->uploadAlert($params);
                break;
            
            case AlertParams::IMPORT_DATA_SUCCESS:
                if (!array_key_exists(AlertParams::DATA_SOURCE_ENTRY, $params) || !array_key_exists(AlertParams::CONNECTED_DATA_SOURCE, $params)) {
                    throw new \Exception('Expect data source entry and connected data source for import data failure');
                }
                $this->alert->importAlert($params);
                break;
            case AlertParams::IMPORT_DATA_FAILURE:
                if (!array_key_exists(AlertParams::ROW, $params) || !array_key_exists(AlertParams::COLUMN, $params)) {
                    throw new \Exception('Expect row and col for upload data failure');
                }
                $this->alert->importAlert($params);
                break;
            default:
                throw new \Exception(sprintf('Unhandle alert code %s', $code));
        }
    }
}