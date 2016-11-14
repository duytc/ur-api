<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

class FormatNumberTransform implements FormatNumberTransformInterface
{
    protected $precision;

    protected $scale;

    protected $thousandSeparator;

    protected $fieldName;

    function __construct($precision, $scale, $thousandSeparator, $fieldName)
    {
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

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    public function transform(Collection $collection)
    {
        // TODO: Implement transform() method.
    }
}