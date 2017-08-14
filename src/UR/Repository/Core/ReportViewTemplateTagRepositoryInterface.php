<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

interface ReportViewTemplateTagRepositoryInterface extends ObjectRepository
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
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @param TagInterface $tag
     * @return ReportViewTemplateTagInterface|null
     */
    public function findByReportViewTemplateAndTag(ReportViewTemplateInterface $reportViewTemplate, TagInterface $tag);
}