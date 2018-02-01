<?php


namespace UR\Service\AutoOptimization;

use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Model\Core\AutoOptimizationConfigInterface;

class ConditionsGenerator implements ConditionsGeneratorInterface
{
    /**
     * @var DataTrainingTableServiceInterface
     */
    private $dataTrainingTableService;

    /**
     * ConditionsGenerator constructor.
     * @param DataTrainingTableServiceInterface $dataTrainingTableService
     */
    public function __construct(DataTrainingTableServiceInterface $dataTrainingTableService)
    {
        $this->dataTrainingTableService = $dataTrainingTableService;
    }

    /**
     * @inheritdoc
     */
    public function generateMultipleConditions(AutoOptimizationConfigInterface $autoOptimizationConfig, array $conditions)
    {
        //Todo: This function should refactor in next version
        $conditions = $this->changeStructureOfConditions($conditions, $autoOptimizationConfig);

        $this->validateConditions($autoOptimizationConfig, $conditions);
        $conditions = $this->replaceNullByAllValuesForOneFactor($autoOptimizationConfig, $conditions);

        $multiConditions = [];
        $vector = [];
        $this->createRecursiveCondition($multiConditions, $vector, $conditions);

        return $multiConditions;
    }

    /**
     * @param $conditions
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return array
     * @throws \Exception
     */
    private function changeStructureOfConditions($conditions, AutoOptimizationConfigInterface $autoOptimizationConfig)
    {
        $newConditions = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            if (array_key_exists('' . self::FACTOR_KEY . '', $condition)) {
                $factor = $condition[self::FACTOR_KEY];
            } else {
                $factor = null;
            }

            if (array_key_exists('' . self::VALUES_KEY . '', $condition)) {
                $values = $condition[self::VALUES_KEY];
            } else {
                $values = null;
            }

            if (array_key_exists('' . self::IS_ALL_KEY . '', $condition)) {
                $allValues = $condition[self::IS_ALL_KEY];
            } else {
                $allValues = null;
            }


            if ($allValues && $this->isTextField($autoOptimizationConfig, $factor)) {
                $newConditions[$factor] = $allValues ? null : $values;
            } else {
                $newConditions[$factor] = $values;
            }
        }

        return $newConditions;
    }


    /**
     * Change the structure values: 10 to values:[10]
     * @param $conditions
     * @return mixed
     */
    public function setValuesToArray($conditions)
    {
        foreach ($conditions as $key => $condition) {
            if (!is_array($condition)) {
                continue;
            }

            if (array_key_exists('' . self::VALUES_KEY . '', $condition)) {
                $values = $condition[self::VALUES_KEY];
            } else {
                $values = null;
            }

            if (!is_array($values)) {
                $condition[self::VALUES_KEY] = [$values];
                $conditions[$key] = $condition;
            }

        }

        return $conditions;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $factor
     * @return bool
     * @throws \Exception
     */
    private function isTextField(AutoOptimizationConfigInterface $autoOptimizationConfig, $factor)
    {
        $allFieldTypes = $autoOptimizationConfig->getFieldTypes();
        if (!array_key_exists($factor, $allFieldTypes)) {
            throw new \Exception("Field %s does not exit in Auto Optimization Config with id =%d", $factor, $autoOptimizationConfig->getId());
        }

        if ($allFieldTypes[$factor] != DataSet::TEXT_FIELD_TYPE_FILTER_KEY) {
            return false;
        }

        return true;
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
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $conditions
     * @return array
     * @throws \Exception
     */
    private function replaceNullByAllValuesForOneFactor(AutoOptimizationConfigInterface $autoOptimizationConfig, array $conditions)
    {
        foreach ($conditions as $factor => $condition) {
            if (!empty($condition) || !$this->isTextField($autoOptimizationConfig, $factor)) {
                continue;
            }

            $conditions[$factor] = $this->dataTrainingTableService->getAllValuesOfOneColumn($autoOptimizationConfig, $factor);
        }

        return $conditions;

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