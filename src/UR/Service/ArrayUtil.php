<?php

namespace UR\Service;

use UR\Exception\InvalidArgumentException;
use UR\Model\ModelInterface;

class ArrayUtil implements ArrayUtilInterface
{
    /**
     * @inheritdoc
     */
    public function array_unique_object(array $objects)
    {
        if (count($objects) < 2) {
            return $objects;
        }

        $mappedObjects = [];
        foreach ($objects as $obj) {
            if (!$obj instanceof ModelInterface) {
                throw new InvalidArgumentException('expect instance of ModelInterface');
            }

            $mappedObjects[$obj->getId()] = $obj;
        }

        return array_values($mappedObjects);
    }
}