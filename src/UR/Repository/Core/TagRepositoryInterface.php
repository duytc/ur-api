<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\AlertPagerParam;
use UR\Model\Core\DataSourceInterface;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface TagRepositoryInterface extends ObjectRepository
{
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

    /**
     * @param string $tagName
     * @return mixed
     */
    public function findByName($tagName);

    /**
     * @param UserRoleInterface $user
     * @param PagerParam $Params
     * @return mixed
     */
    public function getTagsForUserPaginationQuery(UserRoleInterface $user, PagerParam $Params);

    /**
     * @param IntegrationInterface $integration
     * @param PublisherInterface $publisher
     * @return mixed
     */
    public function checkIfUserHasMatchingIntegrationTag(IntegrationInterface $integration, PublisherInterface $publisher);
}