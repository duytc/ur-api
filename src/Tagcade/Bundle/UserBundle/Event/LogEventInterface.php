<?php

namespace Tagcade\Bundle\UserBundle\Event;


interface LogEventInterface
{
    /**
     * User action
     * @return string
     */
    public function getAction();

    /**
     * array represents user data.
     * @return array
     */
    public function getData();
}