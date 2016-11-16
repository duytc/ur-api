<?php


namespace UR\Domain\DTO\Report\Transforms;


interface FormatNumberTransformInterface extends TransformInterface
{
    /**
     * @return mixed
     */
    public function getPrecision();

    /**
     * @return mixed
     */
    public function getThousandSeparator();

    /**
     * @return mixed
     */
    public function getFieldName();
}