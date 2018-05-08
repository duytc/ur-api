<?php

namespace UR\Model\Core;

use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Model\ModelInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage\PubvantageOptimizer;

interface OptimizationIntegrationInterface extends ModelInterface
{
    const ALERT_AUTO_OPTIMIZATION = 'autoOptimization';
    const ALERT_AUTO_OPTIMIZE_AND_NOTICE_ME = 'autoOptimizeAndNotifyMe';
    const ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION = 'notifyMeBeforeMakingChange';

    const SUPPORT_OPTIMIZATION_ALERTS = [
        self::ALERT_AUTO_OPTIMIZATION,
        self::ALERT_AUTO_OPTIMIZE_AND_NOTICE_ME,
        self::ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION
    ];

    const SUPPORT_OPTIMIZATION_FREQUENCIES = [
        DateFilter::DATETIME_DYNAMIC_VALUE_CONTINUOUSLY,
        DateFilter::DATETIME_DYNAMIC_VALUE_30M,
        DateFilter::DATETIME_DYNAMIC_VALUE_1H,
        DateFilter::DATETIME_DYNAMIC_VALUE_4H,
        DateFilter::DATETIME_DYNAMIC_VALUE_12H,
        DateFilter::DATETIME_DYNAMIC_VALUE_24H
    ];

    const SUPPORT_PLATFORM_INTEGRATION = [
        PubvantageOptimizer::PLATFORM_INTEGRATION
    ];

    const ACTIVE_APPLY = 1;
    const ACTIVE_HAS_NOT_CHANGED = 0;
    const ACTIVE_REJECT = -1;

    public function getId();

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @param mixed $name
     */
    public function setName($name);

    /**
     * @return mixed
     */
    public function getIdentifierMapping();

    /**
     * @param mixed $identifierMapping
     */
    public function setIdentifierMapping($identifierMapping);

    /**
     * @return mixed
     */
    public function getIdentifierField();

    /**
     * @param mixed $identifierField
     */
    public function setIdentifierField($identifierField);

    /**
     * @return mixed
     */
    public function getSegments();

    /**
     * @param mixed $segments
     */
    public function setSegments($segments);

    /**
     * @return mixed
     */
    public function getSupplies();

    /**
     * @param mixed $supplies
     */
    public function setSupplies($supplies);

    /**
     * @return mixed
     */
    public function getAdSlots();

    /**
     * @param mixed $adSlots
     */
    public function setAdSlots($adSlots);

    /**
     * @return int
     */
    public function getActive();

    /**
     * @param int $active
     * @return self
     */
    public function setActive($active);

    /**
     * @return mixed
     */
    public function getOptimizationRule();

    /**
     * @param mixed $optimizationRule
     */
    public function setOptimizationRule($optimizationRule);

    /**
     * @return string
     */
    public function getOptimizationAlerts();

    /**
     * @param string $optimizationAlerts
     * @return self
     */
    public function setOptimizationAlerts($optimizationAlerts);

    /**
     * @return boolean
     */
    public function isUserConfirm();

    /**
     * @return boolean
     */
    public function isRequirePendingAlert();

    /**
     * @return boolean
     */
    public function isRequireSuccessAlert();

    /**
     * @return mixed
     */
    public function getOptimizationFrequency();

    /**
     * @param mixed $optimizationFrequency
     * @return self
     */
    public function setOptimizationFrequency($optimizationFrequency);

    /**
     * @return mixed
     */
    public function getStartRescoreAt();

    /**
     * @param mixed $startRescoreAt
     * @return self
     */
    public function setStartRescoreAt($startRescoreAt);

    /**
     * @return mixed
     */
    public function getEndRescoreAt();

    /**
     * @param mixed $endRescoreAt
     * @return self
     */
    public function setEndRescoreAt($endRescoreAt);

    /**
     * @return mixed
     */
    public function getPlatformIntegration();

    /**
     * @param $platformIntegration
     * @return mixed
     */
    public function setPlatformIntegration($platformIntegration);

}