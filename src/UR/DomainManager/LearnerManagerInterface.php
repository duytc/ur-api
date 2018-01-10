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
    public function getLearnerModelByParams(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $type);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @param $type
     * @return mixed
     */
    public function getForecastFactorsValuesByByParams(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $type);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @param $type
     * @return mixed
     */
    public function getCategoricalFieldWeightsByParams(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $type);
}