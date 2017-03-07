<?php

namespace UR\Service\Parser\Transformer\Column;

use Symfony\Component\Config\Definition\Exception\Exception;

class NumberFormat extends AbstractCommonColumnTransform implements ColumnTransformerInterface
{
    const SEPARATOR_COMMA = ',';
    const SEPARATOR_NONE = 'none';
    const DECIMALS = 'decimals';
    const THOUSANDS_SEPARATOR = 'thousandsSeparator';

    private static $supportedThousandsSeparator = [
        ",", "none"
    ];

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

    public function validate()
    {
        if (!is_numeric($this->decimals)) {
            throw new Exception(sprintf('Error at field "%s": Decimals must be number', $this->getField()));
        }

        if (!in_array($this->thousandsSeparator, self::$supportedThousandsSeparator)) {
            throw new Exception(sprintf('Error at field "%s": thousands separator mus be one of %s ', $this->getField(), implode(", ", self::$supportedThousandsSeparator)));
        }
    }
}