<?php


namespace UR\Entity\Core;

use \UR\Model\Core\OptimizationIntegration as OptimizationIntegrationModel;

class OptimizationIntegration extends OptimizationIntegrationModel
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
    protected $alerts;
}