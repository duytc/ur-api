<?php

namespace UR\Service\Parser\Transformer\Column;

class NumberFormat implements ColumnTransformerInterface
{
    /**
     * @var int
     */
    protected $decimals;
    /**
     * @var string
     */
    protected $thousandsSeparator;

    public function __construct($decimals = 2, $thousandsSeparator = ',')
    {
        $this->decimals = $decimals;
        $this->thousandsSeparator = $thousandsSeparator;
    }

    public function transform($value)
    {
        if (!is_numeric($value)) {
            return $value;
        }

        return number_format($value, $this->decimals, '.', $this->thousandsSeparator);
    }
}