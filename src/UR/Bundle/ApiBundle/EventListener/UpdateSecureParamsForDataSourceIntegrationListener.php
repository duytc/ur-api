<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\Integration;

class UpdateSecureParamsForDataSourceIntegrationListener
{
    public function __construct()
    {
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $dataSourceIntegration = $args->getEntity();

        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // encrypt
        $dataSourceIntegration->encryptSecureParams();
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        // only do encrypt if params changed
        if (!$args->hasChangedField('params')) {
            return;
        }

        $dataSourceIntegration = $args->getEntity();
        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        $params = $args->getNewValue('params');
        $oldParams = $args->getOldValue('params');

        // extract all secure params to array [ <param name> => <param value>, ... ]
        $oldSecureParamNameValue = [];
        foreach ($oldParams as $param) {
            if (!is_array($param)
                || !array_key_exists(Integration::PARAM_KEY_KEY, $param)
                || !array_key_exists(Integration::PARAM_KEY_TYPE, $param)
                || !array_key_exists(Integration::PARAM_KEY_VALUE, $param)
            ) {
                continue;
            }

            if ($param[Integration::PARAM_KEY_TYPE] !== Integration::PARAM_TYPE_SECURE) {
                continue;
            }

            $oldSecureParamNameValue[$param[Integration::PARAM_KEY_KEY]] = $param[Integration::PARAM_KEY_VALUE];
        }

        // restore un-changed secure value in params: decode old value then copy to new
        foreach ($params as &$param) {
            if (!is_array($param)
                || !array_key_exists(Integration::PARAM_KEY_KEY, $param)
                || !array_key_exists(Integration::PARAM_KEY_TYPE, $param)
                || !array_key_exists(Integration::PARAM_KEY_VALUE, $param)
            ) {
                continue;
            }

            if ($param[Integration::PARAM_KEY_TYPE] !== Integration::PARAM_TYPE_SECURE) {
                continue;
            }

            if ($param[Integration::PARAM_KEY_VALUE] !== null) {
                continue;
            }

            $oldSecureParamValue = $oldSecureParamNameValue[$param[Integration::PARAM_KEY_KEY]];
            // decode
            $oldSecureParamValue = $dataSourceIntegration->decryptSecureParam($oldSecureParamValue);
            $param[Integration::PARAM_KEY_VALUE] = $oldSecureParamValue;
        }

        $dataSourceIntegration->setParams($params);

        // encrypt
        $dataSourceIntegration->encryptSecureParams();
    }
}