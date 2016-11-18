<?php


namespace UR\Domain\DTO\Report\Transforms;


use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedFieldTransform implements AddCalculatedFieldTransformInterface
{
    const NAME_CALCULATED_FIELD = 'field';
    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_CALCULATED_FIELD = 'defaultValue';

    /**
     * @var string
     */
    protected $fieldName;
    /**
     * @var string
     */
    protected $expression;
    protected $defaultValue;
    protected $language;

    public function __construct(ExpressionLanguage $language, array $addCalculatedField)
    {
        if (!array_key_exists(self::NAME_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::DEFAULT_VALUE_CALCULATED_FIELD, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either name or expression or default value does not exits'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::NAME_CALCULATED_FIELD];
        $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
        $this->defaultValue = $addCalculatedField[self::DEFAULT_VALUE_CALCULATED_FIELD];

    }

    /**
     * @param Collection $collection
     * @return Collection
     */
    public function transform(Collection $collection)
    {
        $columns = $collection->getColumns();
        // new field already existed
        if (array_key_exists($this->fieldName, $columns)) {
            return;
        }

        $row = $collection->getRows();
        $calculatedValue = $this->language->evaluate($this->expression, ['row' => $row]);
        $calculatedValue = $calculatedValue ? $calculatedValue : $this->defaultValue;

        $collection->addColumn($this->fieldName);
        $rows = $collection->getRows();

        foreach ($rows as $row) {
            $row[$this->fieldName] = $calculatedValue;
        }
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
    public function getFieldName()
    {
        return $this->fieldName;
    }
}