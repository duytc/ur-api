<?php

namespace UR\Bundle\AdminApiBundle\Handler;

use UR\Handler\HandlerInterface;

interface UserHandlerInterface extends HandlerInterface
{
    /**
     * @return array
     */
    public function allPublishers();

    public function allActivePublishers();
}