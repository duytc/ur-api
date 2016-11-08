<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class SingleFieldTransform extends AbstractTransform
{
    const TYPE_FORMAT_NUMBER = 1;
    const TYPE_FORMAT_TEXT = 2;

    protected $type;

    protected $fieldName;
}