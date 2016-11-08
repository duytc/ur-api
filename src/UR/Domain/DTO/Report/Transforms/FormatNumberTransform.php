<?php


namespace UR\Domain\DTO\Report\Transforms;


class FormatNumberTransform extends SingleFieldTransform
{
    protected $precision;

    protected $scale;

    protected $thousandSeparator;
}