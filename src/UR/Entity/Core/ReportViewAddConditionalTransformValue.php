<?php

namespace UR\Entity\Core;

use UR\Model\Core\ReportViewAddConditionalTransformValue as ReportViewAddConditionalTransformValueModel;

class ReportViewAddConditionalTransformValue extends ReportViewAddConditionalTransformValueModel
{
    protected $id;
    protected $name;
    protected $defaultValue;
    protected $sharedConditions;
    protected $conditions;
    protected $createdDate;

    protected $publisher;

    public function __construct()
    {
        parent::__construct();
    }
}