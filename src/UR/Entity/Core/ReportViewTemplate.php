<?php

namespace UR\Entity\Core;


use UR\Model\Core\ReportViewTemplate as ReportViewTemplateModel;
use UR\Model\Core\ReportViewTemplateTagInterface;

class ReportViewTemplate extends ReportViewTemplateModel
{
    /** @var  int */
    protected $id;

    /** @var  string */
    protected $name;

    /** @var  bool */
    protected $multiView;

    /** @var  array */
    protected $dataSets;

    /** @var  array */
    protected $reportViews;

    /** @var  array */
    protected $joinConfig;

    /** @var  array */
    protected $transforms;

    /** @var  array */
    protected $formats;

    /** @var  array */
    protected $showInTotal;

    /** @var  bool */
    protected $showDataSetName;

    /** @var  array */
    protected $dimensions;

    /** @var  array */
    protected $metrics;

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