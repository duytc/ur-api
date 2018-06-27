<?php


namespace UR\Entity\Core;

use \UR\Model\Core\OptimizationRule as OptimizationRuleModel;

class OptimizationRule extends OptimizationRuleModel
{
    protected $id;
    protected $name;
    protected $dateField;
    protected $dateRange;
    protected $identifierFields;
    protected $optimizeFields;
    protected $segmentFields;
    protected $token;
    protected $createdDate;
    protected $reportView;
    protected $publisher;
    protected $optimizationIntegrations;
    protected $learners;
    protected $finishLoading;
    protected $lastTrainingDataCheckSum;
    protected $ruleCheckSum;
}