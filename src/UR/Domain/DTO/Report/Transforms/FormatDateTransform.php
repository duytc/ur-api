<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

class FormatDateTransform implements FormatDateTransformInterface
{
    protected $fromFormat;

    protected $toFormat;

    protected $fieldName;

    function __construct($fromFormat, $toFormat, $fieldName)
    {
        $this->fieldName = $fieldName;
        $this->fromFormat = $fromFormat;
        $this->toFormat = $toFormat;
    }

    /**
     * @return mixed
     */
    public function getFromFormat()
    {
        return $this->fromFormat;
    }

    /**
     * @return mixed
     */
    public function getToFormat()
    {
        return $this->toFormat;
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