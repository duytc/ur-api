<?php

namespace UR\Entity\Core;

use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSource as DataSourceModel;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\User\UserEntityInterface;

class DataSource extends DataSourceModel
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
    protected $useIntegration;
    protected $lastActivity;
    protected $missingDate;
    protected $dateRange;
    protected $dateRangeBroken;
    protected $dateRangeDetectionEnabled;
    protected $dateFields;
    /** @var  array */
    protected $dateFieldsFromMetadata;
    protected $dateFormats;
    protected $detectedStartDate;
    protected $detectedEndDate;
    protected $emailAnchorTexts;
    protected $fromMetadata;
    protected $pattern;
    /** @var UserEntityInterface */
    protected $publisher;

    protected $removeDuplicateDates;
    /**
     * @var DataSourceIntegrationInterface[]
     */
    protected $dataSourceIntegrations;

    /**
     * @var DataSourceEntryInterface[]
     */
    protected $dataSourceEntries;

    /**
     * @var ConnectedDataSourceInterface[]
     */
    protected $connectedDataSources;

    /**
     * @var AlertInterface[]
     */
    protected $alerts;

    /** @var  boolean */
    protected $backfillMissingDateRunning;

    /**
     * @var array
     */
    protected $sheets;

    /**
     * @inheritdoc
     *
     * inherit constructor for inheriting all default initialized value
     */
    public function __construct()
    {
        parent::__construct();
    }
}