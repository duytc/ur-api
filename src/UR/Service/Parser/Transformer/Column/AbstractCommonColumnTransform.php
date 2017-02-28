<?php

namespace UR\Service\Parser\Transformer\Column;


abstract class AbstractCommonColumnTransform
{
    private $field;

    /**
     * AbstractCommonColumnTransform constructor.
     * @param $field
     */
    public function __construct($field)
    {
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }
}