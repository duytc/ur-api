<?php

namespace UR\Model\Core;

class ReportViewTemplateTag implements ReportViewTemplateTagInterface
{
    protected $id;

    /** @var  TagInterface */
    protected $tag;

    /**
     * @var ReportViewTemplateInterface
     */
    protected $reportViewTemplate;

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
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @inheritdoc
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * @return ReportViewTemplateInterface
     */
    public function getReportViewTemplate()
    {
        return $this->reportViewTemplate;
    }

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @return self
     */
    public function setReportViewTemplate($reportViewTemplate)
    {
        $this->reportViewTemplate = $reportViewTemplate;

        return $this;
    }
}