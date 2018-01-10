<?php


namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;

class ConditionsGenerator implements ConditionsGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public function generateMultipleConditions(AutoOptimizationConfigInterface $autoOptimizationConfig, array $conditions)
    {
        $this->validateConditions($autoOptimizationConfig, $conditions);
        $multiConditions = [];
        $vector = [];
        $this->createRecursiveCondition($multiConditions, $vector, $conditions);

        return $multiConditions;
    }

    /**
     * @param $conditions
     * @param $vector
     * @param $remainConditions
     */
    private function createRecursiveCondition(&$conditions, $vector, $remainConditions)
    {
        if (empty($remainConditions)) {
            $conditions[] = $vector;
            return;
        }

        /**
         * remainConditions = [
         *      ad_request => [50, 60],
         *      country => ["US", "UK", "VN"],
         *      impression => [30, 20]
         * ]
         */
        $currentField = array_splice($remainConditions, 0, 1);
        /**
         * remainConditions = [
         *      country => ["US", "UK", "VN"],
         *      impression => [30, 20]
         * ]
         */

        $currentFieldName = array_keys($currentField)[0]; //ad_request
        $currentFieldValues = array_values($currentField)[0]; //[50, 60]

        if (empty($currentFieldValues)) {
            $this->createRecursiveCondition($conditions, $vector, $remainConditions);
            return;
        }

        if (!is_array($currentFieldValues)) {
            /** Handle case factor values is string and contain space as "50, 60,    8000",  */
            $currentFieldValues = array_map(function ($value) {
                return trim($value);
            }, explode(",", $currentFieldValues));
        }

        foreach ($currentFieldValues as $fieldValue) {
            $newVector = $vector;
            $newVector[$currentFieldName] = $fieldValue;

            /** Process next field by call recursive */
            $this->createRecursiveCondition($conditions, $newVector, $remainConditions);
        }
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $conditions
     * @throws \Exception
     */
    private function validateConditions(AutoOptimizationConfigInterface $autoOptimizationConfig, $conditions)
    {
        $factors = $autoOptimizationConfig->getFactors();

        if (empty($factors)) {
            throw new \Exception(sprintf('Optimization Config = %d do not have any factors ', $autoOptimizationConfig->getId()));
        }

        foreach ($conditions as $key => $condition) {
            if (!in_array($key, $factors)) {
                throw new \Exception(sprintf('Invalid conditions, factor %s does not supported by auto optimization config = %d', $key, $autoOptimizationConfig->getId()));
            }
        }
    }

    /**
     * Convert conditions to multiple input that use for learner model
     *
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $conditions
     * @return array
     * @throws \Exception
     */
    private function createMultipleConditions(AutoOptimizationConfigInterface $autoOptimizationConfig, array $conditions)
    {
        $arrayElement = $this->getArrayElement($autoOptimizationConfig, $conditions);

        if (count($arrayElement) > 1) {
            throw new \Exception(sprintf('Not support conditions with many factors are arrays '));
        }
        $factor = array_shift($arrayElement);
        $values = $conditions[$factor];

        $multipleConditions = [];
        foreach ($values as $value) {
            $conditions[$factor] = $value;
            $multipleConditions [] = $conditions;
        }

        return $multipleConditions;
    }

    /**
     * Get keys that its value is array
     *
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $conditions
     * @return array
     * @throws \Exception
     */
    private function getArrayElement(AutoOptimizationConfigInterface $autoOptimizationConfig, array $conditions)
    {
        $arrayElement = [];
        $factors = $autoOptimizationConfig->getFactors();

        if (empty($factors)) {
            throw new \Exception(sprintf('Optimization Config = %d do not have any factors ', $autoOptimizationConfig->getId()));
        }

        foreach ($conditions as $key => $condition) {
            if (!in_array($key, $factors)) {
                throw new \Exception(sprintf('Invalid conditions, factor %s does not supported by auto optimization config = %d', $key, $autoOptimizationConfig->getId()));
            }

            if (is_array($condition)) {
                $arrayElement[] = $key;
            }
        }

        return $arrayElement;
    }
}