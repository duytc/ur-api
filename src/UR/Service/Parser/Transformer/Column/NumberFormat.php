<?php

namespace UR\Service\Parser\Transformer\Column;

class NumberFormat implements ColumnTransformerInterface
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

    public function __construct($decimals = 2, $thousandsSeparator = self::SEPARATOR_COMMA)
    {
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