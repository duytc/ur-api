<?php

namespace UR\DomainManager;

use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\User\Role\PublisherInterface;

interface TagManagerInterface extends ManagerInterface
{
    /**
     * @param string $tagName
     * @return mixed
     */
    public function findByName($tagName);

    /**
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param IntegrationInterface $integration
     * @return mixed
     */
    public function findByIntegration(IntegrationInterface $integration);

    /**
     * @param ReportViewTemplateInterface $reportViewTemplateInterface
     * @return mixed
     */
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplateInterface);

    public function checkIfUserHasMatchingIntegrationTag(IntegrationInterface $integration, PublisherInterface $publisher);
}