<?php


namespace UR\Domain\DTO\Report\Transforms;


use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Service\DTO\Collection;

class AddCalculatedFieldTransform extends AbstractTransform implements AddCalculatedFieldTransformInterface
{
    const PRIORITY = 3;
    const NAME_CALCULATED_FIELD = 'field';
    const EXPRESSION_CALCULATED_FIELD = 'expression';
    const DEFAULT_VALUE_CALCULATED_FIELD = 'defaultValue';
    const FIELD_TYPE_KEY = 'type';

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
    protected $type;

    public function __construct(ExpressionLanguage $language, array $addCalculatedField)
    {
        parent::__construct();
        if (!array_key_exists(self::NAME_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::FIELD_TYPE_KEY, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either "field" or "expression" or "type" does not exits'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::NAME_CALCULATED_FIELD];
        $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
        $this->type = $addCalculatedField[self::FIELD_TYPE_KEY];
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return mixed|void
     */
    public function transform(Collection $collection,  array &$metrics, array &$dimensions, $joinBy = null)
    {
        $rows = $collection->getRows();
        foreach($rows as &$row) {
            try {
                $calculatedValue = $this->language->evaluate($this->expression, ['row' => $row]);
            } catch (\Exception $ex) {
                $calculatedValue = 0;
            }

            $calculatedValue = $calculatedValue ? $calculatedValue : $this->defaultValue;
            $row[$this->fieldName] = $calculatedValue;
        }

        $collection->setRows($rows);
        if (!in_array($this->fieldName, $metrics)) {
            $metrics[] = $this->fieldName;
        }
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (array_key_exists($this->fieldName, $metrics) || array_key_exists($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
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

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }
}