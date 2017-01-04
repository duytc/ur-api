<?php

namespace UR\Domain\DTO\Report\JoinBy;

class JoinBy implements JoinByInterface
{
    protected $joinByValue;

    function __construct($joinByValue)
    {
        $this->joinByValue = $joinByValue;
    }

    public function getJoinByValue()
    {
        return $this->joinByValue;
    }
}