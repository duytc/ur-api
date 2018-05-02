<?php

namespace UR\Service\Alert\ActionRequire;


interface ActionRequireFactoryInterface
{
    /**
     * @param $object
     * @param array $extraData
     * @return mixed
     */
    public function createActionRequireAlert($object, $extraData = []);
}