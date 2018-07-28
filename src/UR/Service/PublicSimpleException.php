<?php

namespace UR\Service;

// Use this exception when you need to return custom json
// please see config.yml fos_rest.exception.messages


class PublicSimpleException extends \Exception
{
    public function __construct($message, $code = 500)
    {
        parent::__construct($message, $code);
    }
}