<?php

namespace UnifiedReports\Parser\Transformer\Column;

interface ColumnTransformerInterface
{
    public function transform($value);
}