<?php

namespace UR\Service\Import;

// Use this exception when you need to return custom json
// please see config.yml fos_rest.exception.messages


class PublicImportDataException extends \Exception
{
    public function __construct(array $details, \Exception $e)
    {
        $message = json_encode($details);

        parent::__construct($message, $e->getCode(), $e);
    }
}