<?php


namespace UR\Domain\DTO\Report\Transforms;


interface AddCalculatedFieldTransformInterface extends TransformInterface
{
    /**
     * @return string
     */
    public function getExpression();

    /**
     * @return string
     */
    public function getFieldName();

} 