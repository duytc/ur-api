<?php


namespace UR\Domain\DTO\Report\Transforms;


class FormatNumberTransform extends SingleFieldTransform
{
    protected $precision;

    protected $scale;

    protected $thousandSeparator;

    function __construct($precision, $scale, $thousandSeparator, $fieldName, $type, $target)
    {
        $this->target = $target;
        $this->type = $type;
        $this->fieldName = $fieldName;
        $this->precision = $precision;
        $this->scale = $scale;
        $this->thousandSeparator = $thousandSeparator;
    }

    /**
     * @return mixed
     */
    public function getPrecision()
    {
        return $this->precision;
    }

    /**
     * @return mixed
     */
    public function getScale()
    {
        return $this->scale;
    }

    /**
     * @return mixed
     */
    public function getThousandSeparator()
    {
        return $this->thousandSeparator;
    }
}