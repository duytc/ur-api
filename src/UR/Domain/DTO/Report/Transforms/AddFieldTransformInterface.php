<?php
namespace UR\Domain\DTO\Report\Transforms;


interface AddFieldTransformInterface extends TransformInterface
{
    /**
     * @return mixed
     */
    public function getFieldName();

    /**
     * @return mixed
     */
    public function getValue();
}