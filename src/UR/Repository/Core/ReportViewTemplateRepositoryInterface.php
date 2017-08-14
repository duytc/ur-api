<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\TagInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface ReportViewTemplateRepositoryInterface extends ObjectRepository
{
    /**
     * @param TagInterface $tag
     */
    public function findByTag(TagInterface $tag);

    /**
     * @param PublisherInterface $publisher
     */
    public function findByPublisher(PublisherInterface $publisher);

    /**
     * @param UserRoleInterface $user
     * @param PagerParam $Params
     * @return mixed
     */
    public function getReportViewTemplatesForUserPaginationQuery(UserRoleInterface $user, PagerParam $Params);
}