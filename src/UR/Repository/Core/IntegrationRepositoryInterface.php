<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\TagInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface IntegrationRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $user
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getIntegrationsForUserQuery(UserRoleInterface $user, PagerParam $param);

    /**
     * @param TagInterface $tag
     */
    public function findByTag(TagInterface $tag);
}