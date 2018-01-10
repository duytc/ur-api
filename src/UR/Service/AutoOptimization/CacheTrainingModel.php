<?php

namespace UR\Service\AutoOptimization;

use phpDocumentor\Reflection\Types\Object_;
use Redis;
use UR\Model\Core\AutoOptimizationConfigInterface;

class CacheTrainingModel implements CacheTrainingModelInterface
{
    /** @var Redis */
    private $redis;

    /**
     * CacheTrainingModel constructor.
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritdoc
     */
    public function saveTrainingModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, Object_ $model)
    {
        $this->initRedisConfig();
        $key = $this->buildRedisKeyForIdentifier($autoOptimizationConfig, $identifier);

        $this->redis->set($key, $model);
    }

    /**
     * @inheritdoc
     */
    public function getTrainingModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $this->initRedisConfig();
        $key = $this->buildRedisKeyForIdentifier($autoOptimizationConfig, $identifier);

        if ($this->redis->exists($key)) {
            return $this->redis->get($key);
        }

        return null;
    }

    /**
     * Init Redis Config
     */
    private function initRedisConfig()
    {
        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return string
     */
    private function buildRedisKeyForIdentifier(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        return sprintf("%s_%s", $autoOptimizationConfig->getId(), $identifier);
    }
}