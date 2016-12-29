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
     * @return string
     */
    public function getCanonicalName();

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     * @return self
     */
    public function setType($type);

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url);

    /**
     * @return IntegrationGroupInterface
     */
    public function getIntegrationGroup();

    /**
     * @param IntegrationGroupInterface $integrationGroup
     * @return self
     */
    public function setIntegrationGroup(IntegrationGroupInterface $integrationGroup);
}