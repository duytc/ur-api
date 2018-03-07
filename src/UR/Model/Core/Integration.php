<?php

namespace UR\Model\Core;

use UR\Service\StringUtilTrait;

class Integration implements IntegrationInterface
{
    use StringUtilTrait;

    const PARAM_KEY_KEY = 'key';
    const PARAM_KEY_VALUE = 'value';
    const PARAM_KEY_TYPE = 'type';
    const PARAM_KEY_OPTION_VALUES = 'optionValues';
    const PARAM_KEY_DEFAULT_VALUE = 'defaultValue';

    const PARAM_TYPE_PLAIN_TEXT = 'plainText'; // e.g username, url, ...
    const PARAM_TYPE_DATE = 'date'; // e.g startDate, ...
    const PARAM_TYPE_DYNAMIC_DATE_RANGE = 'dynamicDateRange'; // e.g dateRange, ...
    const PARAM_TYPE_SECURE = 'secure'; // e.g password, token, key, ...
    const PARAM_TYPE_REGEX = 'regex'; // e.g pattern, ...
    const PARAM_TYPE_OPTION = 'option'; // e.g Suply;Demand;Suply by Demand;Demand by Suply, ...
    const PARAM_TYPE_BOOL = 'bool'; // e.g true, false
    const PARAM_TYPE_MULTI_OPTIONS = 'multiOptions'; // e.g (Suply;Demand);Suply by Demand;Demand by Suply, ...

    const SUPPORTED_PARAM_VALUE_REGEX = '/^(?!(\/.*))(?!(.*\/[gmixsuXUAJ]*$))/';

    public static $SUPPORTED_PARAM_TYPES = [
        self::PARAM_TYPE_PLAIN_TEXT,
        self::PARAM_TYPE_DATE,
        self::PARAM_TYPE_DYNAMIC_DATE_RANGE,
        self::PARAM_TYPE_SECURE,
        self::PARAM_TYPE_REGEX,
        self::PARAM_TYPE_OPTION,
        self::PARAM_TYPE_BOOL,
        self::PARAM_TYPE_MULTI_OPTIONS
    ];

    public static $SUPPORTED_PARAM_VALUE_DYNAMIC_DATE_RANGES = [
        'today',
        'yesterday',
        'last 2 days',
        'last 3 days',
        'last 4 days',
        'last 5 days',
        'last 6 days',
        'last week'
    ];

    protected $id;
    protected $name;
    protected $canonicalName;
    protected $params;
    /** @var bool */
    protected $enableForAllUsers;
    /** @var IntegrationPublisherInterface[] */
    protected $integrationPublishers;
    protected $integrationTags;
    public function __construct()
    {
        $this->enableForAllUsers = false;
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
    public function setCanonicalName($canonicalName)
    {
        $this->canonicalName = $canonicalName;
    }

    /**
     * @inheritdoc
     */
    public function getCanonicalName()
    {
        return $this->canonicalName;
    }

    /**
     * @inheritdoc
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @inheritdoc
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isEnableForAllUsers()
    {
        return $this->enableForAllUsers;
    }

    /**
     * @inheritdoc
     */
    public function setEnableForAllUsers($enableForAllUsers)
    {
        $this->enableForAllUsers = (bool)$enableForAllUsers;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIntegrationPublishers()
    {
        return $this->integrationPublishers;
    }

    /**
     * @inheritdoc
     */
    public function setIntegrationPublishers($integrationPublishers)
    {
        $this->integrationPublishers = $integrationPublishers;

        return $this;
    }

    /**
     * @param mixed $integrationTags
     * @return self
     */
    public function setIntegrationTags($integrationTags)
    {
        $this->integrationTags = $integrationTags;
        return $this;
    }
}