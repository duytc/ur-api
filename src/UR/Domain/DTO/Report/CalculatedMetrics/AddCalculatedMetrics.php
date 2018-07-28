<?php

namespace UR\Domain\DTO\Report\CalculatedMetrics;

use SplDoublyLinkedList;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedMetrics implements AddCalculatedMetricsInterface
{
    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_KEY = 'defaultValue';
    const CALCULATION_TYPE = 'calculationType';
    const IS_VISIBLE = 'isVisible';

    const FIELD_NAME_KEY = 'field';
    const TYPE_KEY = 'type';
    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;
    protected $calculationType;
    protected $isVisible;
    protected $fieldName;

    /**
     * @var string
     */
    protected $type;

    public function __construct(ExpressionLanguage $language, array $addCalculatedField, bool $isPostGroup = true)
    {
        if (!array_key_exists(self::FIELD_NAME_KEY, $addCalculatedField)
            || !array_key_exists(self::CALCULATION_TYPE, $addCalculatedField)
            || !array_key_exists(self::TYPE_KEY, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either "field" or "type" or "calculationType" does not exist'));
        }

        if (isset($addCalculatedField[self::CALCULATION_TYPE]) && $addCalculatedField[self::CALCULATION_TYPE] == 1) {
            // user defined so expression should be null and do not execute this one
            $this->expression = null;
        } else {
            if (!array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField))
            {
                throw new \Exception(sprintf('"expression" does not exist'));
            }

            $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
        }

        if (!array_key_exists(self::IS_VISIBLE, $addCalculatedField))
        {
            throw new \Exception(sprintf('isVisible does not exist.'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::FIELD_NAME_KEY];
        $this->type = $addCalculatedField[self::TYPE_KEY];
        $this->isVisible = $addCalculatedField[self::IS_VISIBLE];

        if (isset($addCalculatedField[self::DEFAULT_VALUE_KEY])) {
            $this->defaultValue = $addCalculatedField[self::DEFAULT_VALUE_KEY];
        } else {
            $this->defaultValue = null;
        }
    }

    /**
     * @return array
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * @return string
     */
    public function getCalculationType()
    {
        return $this->calculationType;
    }

    /**
     * @param string $calculationType
     * @return self
     */
    public function setCalculationType($calculationType)
    {
        $this->calculationType = $calculationType;

        return $this;
    }

    /**
     * @return array
     */
    public function getSubFields() {
        $expression = $this->getExpression();

        if (is_null($expression)) {
            return [];
        }

        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return [];
        };

        return $matches[1];
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     * @return self
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function isVisible()
    {
        return $this->isVisible;
    }

    /**
     * @param string $isVisible
     * @return self
     */
    public function setIsVisible($isVisible)
    {
        $this->isVisible = $isVisible;
        return $this;
    }
}