<?php

namespace UR\Behaviors;


use UR\Service\PublicSimpleException;

class JsonSerializationVisitor extends \JMS\Serializer\JsonSerializationVisitor
{
    public function getResult()
    {
        //EXPLICITLY CAST TO ARRAY IF ROOT IS NOT A STRING
        $result = @json_encode(is_string($this->getRoot()) ? $this->getRoot() : (array)$this->getRoot(), $this->getOptions());

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $result;

            case JSON_ERROR_UTF8:
                throw new PublicSimpleException('An error occurred due to file encoding. Please contact your account manager');

            default:
                throw new PublicSimpleException(sprintf('An error occurred while encoding your data (error code %d).', json_last_error()));
        }
    }
}