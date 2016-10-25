<?php

namespace UR\Service\Parser\Transformer\Column;

interface ColumnTransformerInterface
{
    public function transform($value);
}