<?php

namespace UR\Domain\DTO\Report\Transforms;

use SplDoublyLinkedList;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Service\DTO\Collection;

class AddConditionValueTransform extends NewFieldTransform implements TransformInterface
{
    const TRANSFORMS_TYPE = 'addConditionValue';
    const DEFAULT_VALUE_KEY = 'defaultValue';
    const FIELD_KEY = parent::FIELD_NAME_KEY;
    const FIELD_TYPE_KEY = parent::TYPE_KEY;

    const VALUES_KEY = 'values';

    const VALUES_KEY_NAME = 'name';
    const VALUES_KEY_DEFAULT = 'default';

    const VALUES_KEY_SHARED_CONDITIONS = 'sharedConditions';
    const VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_FIELD = 'field';
    const VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_COMPARATOR = 'comparator';
    const VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_VALUE = 'value';

    const VALUES_KEY_CONDITIONS = 'conditions';
    const VALUES_KEY_CONDITIONS_EXPRESSIONS = 'expressions';
    const VALUES_KEY_CONDITIONS_EXPRESSIONS_FIELD = self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_FIELD;
    const VALUES_KEY_CONDITIONS_EXPRESSIONS_COMPARATOR = self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_COMPARATOR;
    const VALUES_KEY_CONDITIONS_EXPRESSIONS_VALUE = self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_VALUE;
    const VALUES_KEY_CONDITIONS_VALUE = 'value';

    /** @var mixed */
    protected $defaultValue;

    /**
     * @var array
     *
     * [
     *      name: ...,
     *      default: ...,
     *      sharedConditions: [
     *          [
     *              conditionField:
     *              conditionComparator:
     *              conditionValue:
     *          ],
     *          ...
     *      ],
     *      conditions:[
     *          [
     *              expressions: [
     *                  [
     *                      conditionField:
     *                      conditionComparator:
     *                      conditionValue:
     *                  ],
     *                  ...
     *              ],
     *              value: ...
     *              ...
     *          ],
     *          ...
     *      ],
     *      ...
     * ]
     */
    protected $values; // json_array

    /**
     * internal var
     *
     * @var array|ReportViewAddConditionalTransformValueInterface[] as
     * [ id => object, ... ]
     */
    protected $mappedValues__;

    public function __construct(array $addConditionValueTransformConfig, bool $isPostGroup = true)
    {
        parent::__construct();

        $this->validateAddConditionValueTransformConfig($addConditionValueTransformConfig);

        $this->fieldName = $addConditionValueTransformConfig[self::FIELD_NAME_KEY];
        $this->type = $addConditionValueTransformConfig[self::TYPE_KEY];
        $this->defaultValue = $addConditionValueTransformConfig[self::DEFAULT_VALUE_KEY];
        $this->values = $addConditionValueTransformConfig[self::VALUES_KEY];
        $this->mappedValues__ = false;

        $this->setIsPostGroup($isPostGroup);
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $outputJoinField
     * @return mixed|void
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        parent::transform($collection, $metrics, $dimensions, $outputJoinField);

        $rows = $collection->getRows();
        $newRows = new SplDoublyLinkedList();

        foreach ($rows as $index => $row) {
            $value = $this->getCalculatedConditionValue($row);

            $row[$this->fieldName] = $value;
            $newRows->push($row);
        }

        unset($rows, $row);
        $collection->setRows($newRows);
    }

    /**
     * @param array $metrics
     * @param array $dimensions
     * @return array|false false if failed
     */
    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return false;
        }

        $metrics[] = $this->fieldName;

        return $metrics;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @return false|\UR\Model\Core\ReportViewAddConditionalTransformValueInterface[]
     */
    public function getMappedValues()
    {
        return $this->mappedValues__;
    }

    /**
     * @param false|\UR\Model\Core\ReportViewAddConditionalTransformValueInterface[] $mappedValues
     */
    public function setMappedValues($mappedValues)
    {
        $this->validateMappedValuesConfig($mappedValues);

        $this->mappedValues__ = $mappedValues;
    }

    /**
     * @param array $addConditionValueTransformConfig
     * @throws InvalidArgumentException
     */
    public static function validateAddConditionValueTransformConfig(array $addConditionValueTransformConfig)
    {
        // validate summary config
        if (!array_key_exists(self::FIELD_NAME_KEY, $addConditionValueTransformConfig)
            || !array_key_exists(self::FIELD_TYPE_KEY, $addConditionValueTransformConfig)
            || !array_key_exists(self::DEFAULT_VALUE_KEY, $addConditionValueTransformConfig)
            || !array_key_exists(self::VALUES_KEY, $addConditionValueTransformConfig)
        ) {
            throw new InvalidArgumentException(sprintf('either "%s" or "%s" or "%s" or "%s" does not exist',
                self::FIELD_NAME_KEY,
                self::FIELD_TYPE_KEY,
                self::DEFAULT_VALUE_KEY,
                self::VALUES_KEY
            ));
        }
    }

    /**
     * @param array $values
     * @throws InvalidArgumentException
     */
    public static function validateMappedValuesConfig(array $values)
    {
        foreach ($values as $valueConfig) {
            if (!$valueConfig instanceof ReportViewAddConditionalTransformValueInterface) {
                throw new InvalidArgumentException('Expected mappedValuesConfig is array of ReportViewAddConditionalTransformValueInterface');
            }

            // validate sharedConditions
            $sharedConditions = $valueConfig->getSharedConditions();
            if (!is_array($sharedConditions)) {
                throw new InvalidArgumentException(sprintf('"%s" is not array',
                    self::VALUES_KEY_SHARED_CONDITIONS
                ));
            }

            foreach ($sharedConditions as $sharedCondition) {
                if (!is_array($sharedCondition)
                    || !array_key_exists(self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_FIELD, $sharedCondition)
                    || !array_key_exists(self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_COMPARATOR, $sharedCondition)
                    || !array_key_exists(self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_VALUE, $sharedCondition)
                ) {
                    throw new InvalidArgumentException(sprintf('"%s" is not array or either "%s" or "%s" or "%s" or "%s" does not exist',
                        'each element in sharedConfigs',
                        self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_FIELD,
                        self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_COMPARATOR,
                        self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_VALUE
                    ));
                }
            }

            // validate conditions
            $conditions = $valueConfig->getConditions();
            if (!is_array($conditions)) {
                throw new InvalidArgumentException(sprintf('"%s" is not array',
                    self::VALUES_KEY_CONDITIONS
                ));
            }

            foreach ($conditions as $condition) {
                if (!is_array($condition)
                    || !array_key_exists(self::VALUES_KEY_CONDITIONS_EXPRESSIONS, $condition)
                    || !array_key_exists(self::VALUES_KEY_CONDITIONS_VALUE, $condition)
                ) {
                    throw new InvalidArgumentException(sprintf('"%s" is not array or either "%s" or "%s" does not exist',
                        self::VALUES_KEY_CONDITIONS,
                        self::VALUES_KEY_CONDITIONS_EXPRESSIONS,
                        self::VALUES_KEY_CONDITIONS_VALUE
                    ));
                }

                $expressions = $condition[self::VALUES_KEY_CONDITIONS_EXPRESSIONS];

                foreach ($expressions as $expression) {
                    if (!is_array($expression)
                        || !array_key_exists(self::VALUES_KEY_CONDITIONS_EXPRESSIONS_FIELD, $expression)
                        || !array_key_exists(self::VALUES_KEY_CONDITIONS_EXPRESSIONS_COMPARATOR, $expression)
                        || !array_key_exists(self::VALUES_KEY_CONDITIONS_EXPRESSIONS_VALUE, $expression)
                    ) {
                        throw new InvalidArgumentException(sprintf('"%s" is not array or either "%s" or "%s" or "%s" or "%s" does not exist',
                            self::VALUES_KEY_CONDITIONS_EXPRESSIONS_FIELD,
                            self::VALUES_KEY_CONDITIONS_EXPRESSIONS_COMPARATOR,
                            self::VALUES_KEY_CONDITIONS_EXPRESSIONS_VALUE
                        ));
                    }
                }
            }
        }
    }

    /**
     * @param array $row
     * @return mixed|null null if failed
     */
    private function getCalculatedConditionValue(array $row)
    {
        if (!is_array($this->values)) {
            return $this->defaultValue;
        }

        foreach ($this->values as $addConditionValueConfig) {
            // not need validate again for addConditionValueConfig due to already validated before

            /*
             * notice - the expected result is:
             * sharedConditions returns true (so, if failed => try next addConditionValueConfig)
             * a condition in conditions returns true (so, if failed => try next condition, if no condition matched => return default value)
             */

            // check sharedConditions
            $sharedConditionConfig = $addConditionValueConfig[self::VALUES_KEY_SHARED_CONDITIONS];
            $isSharedConditionMatched = true;

            foreach ($sharedConditionConfig as $sharedCondition) {
                $sharedConditionField = $sharedCondition[self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_FIELD];
                $sharedConditionComparator = $sharedCondition[self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_COMPARATOR];
                $sharedConditionValue = $sharedCondition[self::VALUES_KEY_SHARED_CONDITIONS_EXPRESSION_VALUE];

                $isMatched = $this->matchCondition($sharedConditionField, $sharedConditionComparator, $sharedConditionValue);
                if (!$isMatched) {
                    break; // exit current loop for sharedCondition
                }
            }

            if (!$isSharedConditionMatched) {
                continue; // try next addConditionValueConfig
            }

            // continue check conditions after sharedConditions passed
            $conditionsConfig = $addConditionValueConfig[self::VALUES_KEY_CONDITIONS];
            $isConditionMatched = true;

            foreach ($conditionsConfig as $conditionConfig) {
                $conditionExpressions = $conditionConfig[self::VALUES_KEY_CONDITIONS_EXPRESSIONS];
                $conditionValue = $conditionConfig[self::VALUES_KEY_CONDITIONS_VALUE];

                foreach ($conditionExpressions as $conditionExpression) {
                    $expressionField = $conditionExpression[self::VALUES_KEY_CONDITIONS_EXPRESSIONS_FIELD];
                    $expressionComparator = $conditionExpression[self::VALUES_KEY_CONDITIONS_EXPRESSIONS_COMPARATOR];
                    $expressionValue = $conditionExpression[self::VALUES_KEY_CONDITIONS_EXPRESSIONS_VALUE];

                    $isMatched = $this->matchCondition($expressionField, $expressionComparator, $expressionValue);
                    if (!$isMatched) {
                        $isConditionMatched = false;
                        break; // exit current loop for condition
                    }
                }

                if (!$isConditionMatched) {
                    continue; // try next condition
                }

                // return conditionValue when a condition is matched
                return $conditionValue;
            }
        }

        // return default value if all addConditionValues (in transform->values) not matched
        return $this->defaultValue;
    }
}