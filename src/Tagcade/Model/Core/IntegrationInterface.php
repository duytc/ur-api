<?php

namespace Tagcade\Model\Core;

use Tagcade\Model\ModelInterface;

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
    public function getType();

    /**
     * @param string $type
     */
    public function setType($type);

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @param string $url
     */
    public function setUrl($url);

    /**
     * @return IntegrationGroupInterface
     */
    public function getIntegrationGroup();

    /**
     * @param IntegrationGroupInterface $integrationGroup
     */
    public function setIntegrationGroup($integrationGroup);
}