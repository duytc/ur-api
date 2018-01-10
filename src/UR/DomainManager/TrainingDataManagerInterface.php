<?php


namespace UR\DomainManager;


use UR\Model\Core\AutoOptimizationConfig;

interface TrainingDataManagerInterface
{
    /**
     * @param AutoOptimizationConfig $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    public function getTrainingDataByAutoOptimizationConfigAndIdentifier(AutoOptimizationConfig $autoOptimizationConfig, $identifier);
}