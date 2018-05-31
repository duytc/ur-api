<?php

namespace UR\Service\Alert\ActionRequire;


interface ActionRequireFactoryInterface
{
    /**
     * @param $optimizationIntegration
     * @param array $extraData
     * @return mixed
     */
    public function createActionRequireAlert($optimizationIntegration, $extraData = []);
}