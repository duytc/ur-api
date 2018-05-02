<?php

namespace UR\Service\Alert\ActionRequire;


interface ActionRequireHandlerInterface
{
    const PARAM_ACTION_NAME = 'actionName';
    const PARAM_ACTION_DATA = 'actionData';

    const PARAM_OPTIMIZATION_INTEGRATION_ID = 'optimizationIntegrationId';
    const PARAM_OPTIMIZATION_CACHE_VERSION = 'cacheVersion';

    const REJECT_OPTIMIZATION_INTEGRATION = 'REJECT_OPTIMIZATION_INTEGRATION';
    const ACTIVE_OPTIMIZATION_INTEGRATION = 'ACTIVE_OPTIMIZATION_INTEGRATION';

    /**
     * @param $actionName
     * @param $actionData
     * @return mixed
     */
    public function handleActionRequired($actionName, $actionData);
}