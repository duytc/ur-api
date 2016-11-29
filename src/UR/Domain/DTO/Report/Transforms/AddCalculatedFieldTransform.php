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
        parent::__construct();
        if (!array_key_exists(self::NAME_CALCULATED_FIELD, $addCalculatedField)
            || !array_key_exists(self::EXPRESSION_CALCULATED_FIELD, $addCalculatedField)
//            || !array_key_exists(self::DEFAULT_VALUE_CALCULATED_FIELD, $addCalculatedField)
        ) {
            throw new \Exception(sprintf('either name or expression or default value does not exits'));
        }

        $this->language = $language;
        $this->fieldName = $addCalculatedField[self::NAME_CALCULATED_FIELD];
        $this->expression = $addCalculatedField[self::EXPRESSION_CALCULATED_FIELD];
//        $this->defaultValue = $addCalculatedField[self::DEFAULT_VALUE_CALCULATED_FIELD];

    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed|void
     */
    public function transform(Collection $collection,  array $metrics, array $dimensions)
    {
        $columns = $collection->getColumns();
        // new field already existed
        if (array_key_exists($this->fieldName, $columns)) {
            return;
        }

        $rows = $collection->getRows();
        foreach($rows as &$row) {
            $calculatedValue = $this->language->evaluate($this->expression, ['row' => $row]);
            $calculatedValue = $calculatedValue ? $calculatedValue : $this->defaultValue;
            $row[$this->fieldName] = $calculatedValue;
        }

        $collection->addColumn($this->fieldName);
        $collection->setRows($rows);
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        $dimensions[] = $this->fieldName;
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