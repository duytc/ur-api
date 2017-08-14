<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface IntegrationTagInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return IntegrationInterface
     */
    public function getIntegration();

    /**
     * @param IntegrationInterface $integration
     * @return self
     */
    public function setIntegration($integration);

    /**
     * @return TagInterface
     */
    public function getTag();

    /**
     * @param TagInterface $tag
     * @return self
     */
    public function setTag($tag);
}