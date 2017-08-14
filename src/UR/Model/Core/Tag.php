<?php

namespace UR\Model\Core;

class Tag implements TagInterface
{
    protected $id;
    protected $name;

    /** @var  UserTagInterface[] */
    protected $userTags;

    /** @var  IntegrationTagInterface[] */
    protected $integrationTags;

    /** @var  ReportViewTemplateTagInterface[] */
    protected $reportViewTemplateTags;

    public function __construct()
    {
    }

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

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUserTags()
    {
        return $this->userTags;
    }

    /**
     * @inheritdoc
     */
    public function setUserTags($userTags)
    {
        $this->userTags = $userTags;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIntegrationTags()
    {
        return $this->integrationTags;
    }

    /**
     * @inheritdoc
     */
    public function setIntegrationTags($integrationTags)
    {
        $this->integrationTags = $integrationTags;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReportViewTemplateTags()
    {
        return $this->reportViewTemplateTags;
    }

    /**
     * @inheritdoc
     */
    public function setReportViewTemplateTags($reportViewTemplateTags)
    {
        $this->reportViewTemplateTags = $reportViewTemplateTags;

        return $this;
    }
}