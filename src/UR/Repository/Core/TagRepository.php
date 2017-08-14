<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class TagRepository extends EntityRepository implements TagRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.userTags', 'ut')
            ->where('ut.publisher = :publisher')
            ->setParameter('publisher', $publisher);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByIntegration(IntegrationInterface $integration)
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.integrationTags', 'it')
            ->where('it.integration = :integration')
            ->setParameter('integration', $integration);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByReportViewTemplate(ReportViewTemplateInterface $reportViewTemplateInterface)
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.reportViewTemplateTags', 'rt')
            ->where('rt.reportViewTemplate = :reportViewTemplateInterface')
            ->setParameter('reportViewTemplateInterface', $reportViewTemplateInterface);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByName($tagName)
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.name = :name')
            ->setParameter('name', $tagName);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @inheritdoc
     */
    public function getTagsForUserPaginationQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('t.name', ':searchKey'),
                    $qb->expr()->like('t.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }
        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('t.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('t.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    /**
     * @param UserRoleInterface $user
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $this->createQueryBuilder('t');
    }
}