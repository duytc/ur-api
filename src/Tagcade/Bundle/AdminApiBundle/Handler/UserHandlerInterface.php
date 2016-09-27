<?php

namespace Tagcade\Bundle\AdminApiBundle\Handler;

use Tagcade\Handler\HandlerInterface;

interface UserHandlerInterface extends HandlerInterface
{
    /**
     * @return array
     */
    public function allPublishers();

    public function allActivePublishers();
}