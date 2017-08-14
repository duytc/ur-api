<?php

namespace UR\Model\Core;

use DateTime;
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
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return self
     */
    public function addDataSourceEntry(DataSourceEntryInterface $dataSourceEntry);

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

    /**
     * @return mixed
     */
    public function getNumOfFiles();

    /**
     * @param mixed $numOfFiles
     * @return self
     */
    public function setNumOfFiles($numOfFiles);

    /**
     * @return array
     */
    public function getMissingDate();

    /**
     * @param array $missingDate
     * @return self
     */
    public function setMissingDate($missingDate);

    /**
     * @return boolean
     */
    public function isDateRangeBroken();

    /**
     * @param boolean $dateRangeBroken
     * @return self
     */
    public function setDateRangeBroken($dateRangeBroken);

    /**
     * @return boolean
     */
    public function isDateRangeDetectionEnabled();

    /**
     * @param boolean $dateRangeDetectionEnabled
     * @return self
     */
    public function setDateRangeDetectionEnabled($dateRangeDetectionEnabled);

    /**
     * @return mixed
     */
    public function getDateFields();

    /**
     * @param mixed $dateFields
     * @return self
     */
    public function setDateFields($dateFields);

    /**
     * @return mixed
     */
    public function getDateFormats();

    /**
     * @param mixed $dateFormats
     * @return self
     */
    public function setDateFormats($dateFormats);

    /**
     * @return mixed
     */
    public function getDetectedStartDate();

    /**
     * @param mixed $detectedStartDate
     * @return self
     */
    public function setDetectedStartDate($detectedStartDate);

    /**
     * @return mixed
     */
    public function getDetectedEndDate();

    /**
     * @param mixed $detectedEndDate
     * @return self
     */
    public function setDetectedEndDate($detectedEndDate);

    /**
     * @return array
     */
    public function getDateRange();

    /**
     * @param array $dateRange
     * @return self
     */
    public function setDateRange($dateRange);

    /**
     * @return boolean
     */
    public function isFromMetadata();

    /**
     * @param boolean $fromMetadata
     * @return self
     */
    public function setFromMetadata($fromMetadata);

    /**
     * @return string
     */
    public function getPattern();

    /**
     * @param string $pattern
     * @return self
     */
    public function setPattern($pattern);

    /**
     * @return mixed
     */
    public function getEmailAnchorTexts();

    /**
     * @param mixed $emailAnchorTexts
     * @return self
     */
    public function setEmailAnchorTexts($emailAnchorTexts);

    /**
     * @return array
     */
    public function getDateFieldsFromMetadata();

    /**
     * @param array $dateFieldsFromMetadata
     * @return self
     */
    public function setDateFieldsFromMetadata($dateFieldsFromMetadata);

    /**
     * @return boolean|null
     */
    public function getBackfillMissingDateRunning();

    /**
     * @param boolean $backfillMissingDateRunning
     * @return self
     */
    public function setBackfillMissingDateRunning($backfillMissingDateRunning);
}