<?php

namespace UR\Model\Core;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class DataSource implements DataSourceInterface
{
    protected $id;
    protected $name;
    protected $format;
    protected $alertSetting;
    protected $apiKey;
    protected $urEmail;
    protected $enable;
    protected $detectedFields;
    protected $nextAlertTime;
    protected $useIntegration; // bool, true if use integration
    protected $lastActivity;
    protected $numOfFiles;
    protected $dateFields;
    /** @var  array */
    protected $dateFieldsFromMetadata;
    protected $dateFormats;
    protected $detectedStartDate;
    protected $detectedEndDate;
    protected $emailAnchorTexts;
    /**
     * @var bool
     */
    protected $fromMetadata;

    /**
     * @var string
     */
    protected $pattern;
    /**
     * @var array
     */
    protected $dateRange;
    /**
     * @var array
     */
    protected $missingDate;
    /**
     * @var bool
     */
    protected $dateRangeBroken;
    /**
     * @var bool
     */
    protected $dateRangeDetectionEnabled;

    /** @var UserEntityInterface */
    protected $publisher;

    /**
     * @var DataSourceEntryInterface[]
     */
    protected $dataSourceEntries;

    /**
     * @var IntegrationInterface[]
     */
    protected $dataSourceIntegrations;

    /**
     * @var ConnectedDataSourceInterface[]
     */
    protected $connectedDataSources;

    public function __construct()
    {
        $this->enable = true;
        $this->numOfFiles = 0;
        $this->missingDate = [];
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPublisherId()
    {
        if (!$this->publisher) {
            return null;
        }

        return $this->publisher->getId();
    }

    /**
     * @inheritdoc
     */
    public function setPublisher(PublisherInterface $publisher)
    {
        $this->publisher = $publisher->getUser();
        return $this;
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @inheritdoc
     */
    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceIntegrations()
    {
        return $this->dataSourceIntegrations;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceIntegrations($dataSourceIntegrations)
    {
        $this->dataSourceIntegrations = $dataSourceIntegrations;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @inheritdoc
     */
    public function setApiKey($apiKey = null)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUrEmail()
    {
        return $this->urEmail;
    }

    /**
     * @inheritdoc
     */
    public function setUrEmail($urEmail)
    {
        $this->urEmail = $urEmail;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntries()
    {
        return $this->dataSourceEntries;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceEntries($dataSourceEntries)
    {
        $this->dataSourceEntries = $dataSourceEntries;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addDataSourceEntry(DataSourceEntryInterface $dataSourceEntries)
    {
        // if array
        if (is_array($this->dataSourceEntries)) {
            $this->dataSourceEntries[] = $dataSourceEntries;

            return $this;
        }

        // else => use collection
        if (!$this->dataSourceEntries instanceof Collection) {
            $this->dataSourceEntries = new ArrayCollection();
        }

        $this->dataSourceEntries->add($dataSourceEntries);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAlertSetting()
    {
        return $this->alertSetting;
    }

    /**
     * @inheritdoc
     */
    public function setAlertSetting($alertSetting)
    {
        $this->alertSetting = $alertSetting;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * @inheritdoc
     */
    public function setEnable($enable)
    {
        $this->enable = $enable;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSources()
    {
        return $this->connectedDataSources;
    }

    /**
     * @inheritdoc
     */
    public function setConnectedDataSources($connectedDataSources)
    {
        $this->connectedDataSources = $connectedDataSources;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDetectedFields()
    {
        return $this->detectedFields;
    }

    /**
     * @inheritdoc
     */
    public function setDetectedFields($detectedFields)
    {
        $this->detectedFields = $detectedFields;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUseIntegration()
    {
        return $this->useIntegration;
    }

    /**
     * @inheritdoc
     */
    public function setUseIntegration($useIntegration)
    {
        $this->useIntegration = $useIntegration;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNextAlertTime()
    {
        return $this->nextAlertTime;
    }

    /**
     * @inheritdoc
     */
    public function setNextAlertTime($nextAlertTime)
    {
        $this->nextAlertTime = $nextAlertTime;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLastActivity()
    {
        return $this->lastActivity;
    }

    /**
     * @inheritdoc
     */
    public function setLastActivity($lastActivity)
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNumOfFiles()
    {
        return $this->numOfFiles;
    }

    /**
     * @inheritdoc
     */
    public function setNumOfFiles($numOfFiles)
    {
        $this->numOfFiles = $numOfFiles;
        return $this;
    }

    /**
     * @return array
     */
    public function getMissingDate()
    {
        return $this->missingDate;
    }

    /**
     * @param array $missingDate
     * @return self
     */
    public function setMissingDate($missingDate)
    {
        $this->missingDate = $missingDate;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDateRangeBroken()
    {
        return $this->dateRangeBroken;
    }

    /**
     * @param boolean $dateRangeBroken
     * @return self
     */
    public function setDateRangeBroken($dateRangeBroken)
    {
        $this->dateRangeBroken = $dateRangeBroken;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDateRangeDetectionEnabled()
    {
        return $this->dateRangeDetectionEnabled;
    }

    /**
     * @param boolean $dateRangeDetectionEnabled
     * @return self
     */
    public function setDateRangeDetectionEnabled($dateRangeDetectionEnabled)
    {
        $this->dateRangeDetectionEnabled = $dateRangeDetectionEnabled;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateFields()
    {
        return $this->dateFields;
    }

    /**
     * @param mixed $dateFields
     * @return self
     */
    public function setDateFields($dateFields)
    {
        $this->dateFields = $dateFields;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDateFormats()
    {
        return $this->dateFormats;
    }

    /**
     * @param mixed $dateFormats
     * @return self
     */
    public function setDateFormats($dateFormats)
    {
        $this->dateFormats = $dateFormats;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDetectedStartDate()
    {
        return $this->detectedStartDate;
    }

    /**
     * @param mixed $detectedStartDate
     * @return self
     */
    public function setDetectedStartDate($detectedStartDate)
    {
        $this->detectedStartDate = $detectedStartDate;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDetectedEndDate()
    {
        return $this->detectedEndDate;
    }

    /**
     * @param mixed $detectedEndDate
     * @return self
     */
    public function setDetectedEndDate($detectedEndDate)
    {
        $this->detectedEndDate = $detectedEndDate;
        return $this;
    }

    /**
     * @return array
     */
    public function getDateRange()
    {
        return $this->dateRange;
    }

    /**
     * @param array $dateRange
     * @return self
     */
    public function setDateRange($dateRange)
    {
        $this->dateRange = $dateRange;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isFromMetadata()
    {
        return $this->fromMetadata;
    }

    /**
     * @param boolean $fromMetadata
     * @return self
     */
    public function setFromMetadata($fromMetadata)
    {
        $this->fromMetadata = $fromMetadata;
        return $this;
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @param string $pattern
     * @return self
     */
    public function setPattern($pattern)
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmailAnchorTexts()
    {
        return $this->emailAnchorTexts;
    }

    /**
     * @param mixed $emailAnchorTexts
     * @return self
     */
    public function setEmailAnchorTexts($emailAnchorTexts)
    {
        $this->emailAnchorTexts = $emailAnchorTexts;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDateFieldsFromMetadata()
    {
        return $this->dateFieldsFromMetadata;
    }

    /**
     * @inheritdoc
     */
    public function setDateFieldsFromMetadata($dateFieldsFromMetadata)
    {
        $this->dateFieldsFromMetadata = $dateFieldsFromMetadata;

        return $this;
    }
}