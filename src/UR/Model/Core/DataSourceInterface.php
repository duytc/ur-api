<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceInterface extends ModelInterface
{
    const JSON_FORMAT = 'json';
    const CSV_FORMAT = 'csv';
    const EXCEL_FORMAT = 'excel';

    /**
     * @return mixed
     */
    public function getAlertSetting();

    /**
     * @param mixed $alertSetting
     * @return self
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
     * @return self
     */
    public function setApiKey($apiKey = null);


    /**
     * @return string
     */
    public function getUrEmail();

    /**
     * @param string $urEmail
     * @return self
     */
    public function setUrEmail($urEmail);

    /**
     * @return mixed
     */
    public function getDataSourceEntries();

    /**
     * @param mixed $dataSourceEntries
     * @return self
     */
    public function setDataSourceEntries($dataSourceEntries);

    /**
     * @return mixed
     */
    public function getEnable();

    /**
     * @param mixed $enable
     * @return self
     */
    public function setEnable($enable);

    /**
     * @return ConnectedDataSourceInterface[]
     */
    public function getConnectedDataSources();

    /**
     * @param ConnectedDataSourceInterface[] $connectedDataSources
     * @return self
     */
    public function setConnectedDataSources($connectedDataSources);

    /**
     * @return mixed
     */
    public function getDetectedFields();

    /**
     * @param mixed $detectedFields
     * @return self
     */
    public function setDetectedFields($detectedFields);

    /**
     * @return \DateTime|null
     */
    public function getNextAlertTime();

    /**
     * @param mixed $nextAlertTime
     * @return self
     */
    public function setNextAlertTime($nextAlertTime);

    /**
     * @return bool
     */
    public function getUseIntegration();

    /**
     * @param bool $useIntegration
     * @return self
     */
    public function setUseIntegration($useIntegration);

    /**
     * @return mixed
     */
    public function getLastActivity();

    /**
     * @param mixed $lastActivity
     * @return self
     */
    public function setLastActivity($lastActivity);
}