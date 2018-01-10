<?php


namespace UR\Entity\Core;


use UR\Model\Core\Learner as LearnerModel;

class Learner extends LearnerModel
{
    protected $id;
    protected $identifier;
    protected $model;
    protected $type;
    protected $autoOptimizationConfig;
    protected $updatedDate;
    protected $forecastFactorValues;
    protected $categoricalFieldWeights;
}