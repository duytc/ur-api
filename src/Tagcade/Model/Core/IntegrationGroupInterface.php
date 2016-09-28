<?php

namespace Tagcade\Model\Core;

use Tagcade\Model\ModelInterface;

interface IntegrationGroupInterface extends ModelInterface
{
    /**
     * @return string|null
     */
    public function getName();

    /**
     * @param string $name
     * @return self
     */
    public function setName($name);
}