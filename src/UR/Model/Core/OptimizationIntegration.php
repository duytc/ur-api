<?php

namespace UR\Model\Core;

class OptimizationIntegration implements OptimizationIntegrationInterface
{
    protected $id;
    protected $name;
    protected $identifierMapping;
    protected $identifierField;
    protected $segments;
    protected $supplies;
    protected $adSlots;
    protected $active;
    protected $optimizationRule;
    protected $optimizationAlerts;
    protected $optimizationFrequency;
    protected $startRescoreAt;
    protected $endRescoreAt;
    protected $platformIntegration;
    /**
     * @var AlertInterface[]
     */
    protected $alerts;

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
    }

    /**
     * @inheritdoc
     */
    public function getIdentifierMapping()
    {
        return $this->identifierMapping;
    }

    /**
     * @inheritdoc
     */
    public function setIdentifierMapping($identifierMapping)
    {
        $this->identifierMapping = $identifierMapping;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifierField()
    {
        return $this->identifierField;
    }

    /**
     * @inheritdoc
     */
    public function setIdentifierField($identifierField)
    {
        $this->identifierField = $identifierField;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSegments()
    {
        return $this->segments;
    }

    /**
     * @inheritdoc
     */
    public function setSegments($segments)
    {
        $this->segments = $segments;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSupplies()
    {
        return $this->supplies;
    }

    /**
     * @inheritdoc
     */
    public function setSupplies($supplies)
    {
        $this->supplies = $supplies;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAdSlots()
    {
        return $this->adSlots;
    }

    /**
     * @inheritdoc
     */
    public function setAdSlots($adSlots)
    {
        $this->adSlots = $adSlots;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @inheritdoc
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $active;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationFrequency()
    {
        return $this->optimizationFrequency;
    }

    /**
     * @inheritdoc
     */
    public function setOptimizationFrequency($optimizationFrequency)
    {
        $this->optimizationFrequency = $optimizationFrequency;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getStartRescoreAt()
    {
        return $this->startRescoreAt;
    }

    /**
     * @inheritdoc
     */
    public function setStartRescoreAt($startRescoreAt)
    {
        $this->startRescoreAt = $startRescoreAt;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getEndRescoreAt()
    {
        return $this->endRescoreAt;
    }

    /**
     * @inheritdoc
     */
    public function setEndRescoreAt($endRescoreAt)
    {
        $this->endRescoreAt = $endRescoreAt;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPlatformIntegration()
    {
        return $this->platformIntegration;
    }

    /**
     * @inheritdoc
     */
    public function setPlatformIntegration($platformIntegration)
    {
        $this->platformIntegration = $platformIntegration;
        return $this;
    }


    /**
     * @inheritdoc
     */
    public function getOptimizationRule()
    {
        return $this->optimizationRule;
    }

    /**
     * @inheritdoc
     */
    public function setOptimizationRule($optimizationRule)
    {
        $this->optimizationRule = $optimizationRule;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationAlerts()
    {
        return $this->optimizationAlerts;
    }

    /**
     * @inheritdoc
     */
    public function setOptimizationAlerts($optimizationAlerts)
    {
        $this->optimizationAlerts = $optimizationAlerts;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isUserConfirm()
    {
        if ($this->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION) {
            if ($this->getActive() == OptimizationIntegrationInterface::ACTIVE_APPLY) {
                return true;
            } elseif ($this->getActive() == OptimizationIntegrationInterface::ACTIVE_REJECT) {
                return false;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function isRequirePendingAlert()
    {
        if ($this->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION) {
            if ($this->getActive() == OptimizationIntegrationInterface::ACTIVE_APPLY) {
                return false;
            } elseif ($this->getActive() == OptimizationIntegrationInterface::ACTIVE_REJECT) {
                return true;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function isRequireSuccessAlert()
    {
        if ($this->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_AUTO_OPTIMIZATION) {
            return false;
        }

        if ($this->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_AUTO_OPTIMIZE_AND_NOTICE_ME) {
            return true;
        }

        if ($this->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION) {
            if ($this->getActive() == OptimizationIntegrationInterface::ACTIVE_APPLY) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }
}