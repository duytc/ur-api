<?php

namespace UR\Service\Parser\Transformer\Collection;


class AbstractTransform
{
    protected $priority;

    /**
     * AbstractTransform constructor.
     * @param $priority
     */
    public function __construct($priority)
    {
        $this->priority = $priority;
    }

    /**
     * @return mixed
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param mixed $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }
}