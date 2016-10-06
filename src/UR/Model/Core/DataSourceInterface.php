<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getAlertSetting();
    /**
     * @param mixed $alertSetting
     */

    public function setAlertSetting($alertSetting);

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
     * @return string|null
     */
    public function getFormat();

    /**
     * @param string $format
     * @return self
     */
    public function setFormat($format);

    /**
     * @return PublisherInterface|null
     */
    public function getPublisher();

    /**
     * @return int|null
     */
    public function getPublisherId();

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher(PublisherInterface $publisher);

    /**
     * @return IntegrationInterface[]
     */
    public function getDataSourceIntegrations();

    /**
     * @param IntegrationInterface[] $dataSourceIntegrations
     * @return self
     */
    public function setDataSourceIntegrations($dataSourceIntegrations);

    /**
     * @return mixed
     */
    public function getApiKey();

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey);

    /**
     * @return mixed
     */
    public function generateApiKey();
}