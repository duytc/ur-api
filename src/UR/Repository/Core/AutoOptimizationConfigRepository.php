<?php


namespace UR\Repository\Core;


use Doctrine\ORM\EntityRepository;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;

class AutoOptimizationConfigRepository extends EntityRepository implements AutoOptimizationConfigRepositoryInterface
{
    const TRANSFORM_TYPE_KEY = 'type';
    const ADD_CONDITION_VALUE_TRANSFORM_TYPE = 'addConditionValue';
    const ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY = 'values';
    /**
     * @inheritdoc
     */
    public function removeAddConditionalTransformValue($id)
    {
        $autoOptimizationConfigs = $this->findAll();

        /** @var AutoOptimizationConfigInterface[] $autoOptimizationConfigs */
        foreach ($autoOptimizationConfigs as $autoOptimizationConfig) {
            $newTransforms = [];
            $transforms = $autoOptimizationConfig->getTransforms();

            if (is_null($transforms)) {
                continue;
            }

            foreach ($transforms as $transform) {
                //$transform = json_decode($transform, true);
                if ($transform[self::TRANSFORM_TYPE_KEY] === self::ADD_CONDITION_VALUE_TRANSFORM_TYPE) {
                    $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                    foreach ($fields as &$field) {
                        $ids = $field[self::ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY];
                        $key = array_search($id, $ids);
                        if ($key !== false) {
                            unset($ids[$key]);
                            $field[self::ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY] = array_values($ids);
                        }
                    }
                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                }
                $newTransforms[] = $transform;
            }
            $autoOptimizationConfig->setTransforms($newTransforms);

            $this->_em->persist($autoOptimizationConfig);
        }
        $this->_em->flush();
    }
}