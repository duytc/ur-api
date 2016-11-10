<?php


namespace UR\Domain\DTO\Report\Transforms;


class FormatDateTransform extends SingleFieldTransform
{
    protected $fromFormat;

    protected $toFormat;

    function __construct($fromFormat, $toFormat, $fieldName, $type, $target)
    {
        $this->target = $target;
        $this->type = $type;
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
}