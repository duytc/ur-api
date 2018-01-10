<?php


namespace UR\DomainManager;


use UR\Model\Core\AutoOptimizationConfigInterface;

interface LearnerManagerInterface extends ManagerInterface
{
    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    public function getLearnerModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier);
}