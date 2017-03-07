<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface IntegrationInterface extends ModelInterface
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

    /**
     * @param string $canonicalName
     * @return self
     */
    public function setCanonicalName($canonicalName);

    /**
     * @return string
     */
    public function getCanonicalName();

    /**
     * @return string
     */
    public function getParams();

    /**
     * @param string $params
     * @return self
     */
    public function setParams($params);

    /**
     * @return IntegrationPublisherModelInterface[] 
     */
    public function getIntegrationPublishers();

    /**
     * @param mixed $integrationPublishers
     */
    public function setIntegrationPublishers($integrationPublishers);
}