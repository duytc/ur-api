<?php


namespace UR\Domain\DTO\Report\Transforms;


interface FormatDateTransformInterface extends TransformInterface
{
    /**
     * @return mixed
     */
    public function getFromFormat();

    /**
     * @return mixed
     */
    public function getToFormat();

    /**
     * @return mixed
     */
    public function getFieldName();
}