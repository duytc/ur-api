<?php

namespace UR\Entity\Core;

use UR\Model\Core\IntegrationTagInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\Core\Tag as TagModel;
use UR\Model\Core\UserTagInterface;

class Tag extends TagModel
{
    protected $id;
    protected $name;

    /** @var  UserTagInterface[] */
    protected $userTags;

    /** @var  IntegrationTagInterface[] */
    protected $integrationTags;

    /** @var  ReportViewTemplateTagInterface[] */
    protected $reportViewTemplateTags;

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