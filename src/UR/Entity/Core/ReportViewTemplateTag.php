<?php

namespace UR\Entity\Core;


use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\TagInterface;
use UR\Model\Core\ReportViewTemplateTag as ReportViewTemplateTagModel;

class ReportViewTemplateTag extends ReportViewTemplateTagModel
{
    /** @var  TagInterface */
    protected $tag;

    /**
     * @var ReportViewTemplateInterface
     */
    protected $reportViewTemplate;

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