<?php


namespace UR\DomainManager;


use UR\Model\Core\AutoOptimizationConfig;

class TrainingDataManager implements TrainingDataManagerInterface
{
    /**
     * @var AutoOptimizationConfigManager
     */
    private $autoOptimizationConfigManager;

    /**
     * TrainingDataManager constructor.
     * @param AutoOptimizationConfigManager $autoOptimizationConfigManager
     */
    public function __construct(AutoOptimizationConfigManager $autoOptimizationConfigManager)
    {
        $this->autoOptimizationConfigManager = $autoOptimizationConfigManager;
    }


    /**
     * @inheritdoc
     */
    public function getTrainingDataByAutoOptimizationConfigAndIdentifier(AutoOptimizationConfig $autoOptimizationConfig, $identifier)
    {
        $rawData = [];

        // TODO: Implement getTrainingDataByAutoOptimizationConfigAndIdentifier() method.

        return $rawData;
    }
}