<?php

namespace Tagcade\Service;

use Tagcade\Model\ModelInterface;

interface ArrayUtilInterface
{
    /**
     * array_unique for array of objects
     * @param ModelInterface[] $objects
     * @return ModelInterface[]
     */
    public function array_unique_object(array $objects);
}