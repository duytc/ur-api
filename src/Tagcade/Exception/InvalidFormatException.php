<?php

namespace Tagcade\Exception;


class InvalidFormatException extends \Exception{

    function __construct($message)
    {
        parent::__construct($message);
    }
}