<?php

namespace UR\DomainManager;

use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

interface ReportViewTemplateTagManagerInterface extends ManagerInterface
{
    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @return mixed
     */
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplate);

    /**
     * @param TagInterface $tag
     * @return mixed
     */
    public function findByTag(TagInterface $tag);

    /**
     * @param PublisherInterface $publisher
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @param TagInterface $tag
     * @return ReportViewTemplateTagInterface|null
     */
    public function findByReportViewTemplateAndTag(ReportViewTemplateInterface $reportViewTemplate, TagInterface $tag);
}