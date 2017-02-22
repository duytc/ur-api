<?php

namespace UR\Service\Parser\Transformer\Column;

class NumberFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    const SEPARATOR_COMMA = ',';
    const SEPARATOR_NONE = 'none';

    /**
     * @var int
     */
    protected $decimals;
    /**
     * @var string
     */
    protected $thousandsSeparator;

    public function __construct($field, $decimals, $thousandsSeparator)
    {
        parent::__construct($field);
        $this->decimals = $decimals;
        $this->thousandsSeparator = $thousandsSeparator;
    }

    public function transform($value)
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $thousandsSeparator = $this->thousandsSeparator === self::SEPARATOR_NONE ? '' : $this->thousandsSeparator;

        return number_format($value, $this->decimals, '.', $thousandsSeparator);
    }
}