<?php


namespace UR\DomainManager;


use UR\Model\Core\AutoOptimizationConfigInterface;

interface LearnerManagerInterface extends ManagerInterface
{

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @param $type
     * @return mixed
     */
    public function getLearnerByParams(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $type);
}