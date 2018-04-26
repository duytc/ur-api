<?php


namespace UR\Entity\Core;

use UR\Model\Core\Learner as LearnerModel;

class Learner extends LearnerModel
{
    protected $id;
    protected $identifier;
    protected $segmentValues;
    protected $optimizeField;
    protected $modelPath;
    protected $mathModel;
    protected $metricsPredictiveValues;
    protected $createdDate;
    protected $updatedDate;
    protected $optimizationRule;
}