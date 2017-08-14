<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\TagInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class ReportViewTemplateRepository extends EntityRepository implements ReportViewTemplateRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag)
    {
        $qb = $this->createQueryBuilder('rpt')
            ->leftJoin('rpt.reportViewTemplateTags', 'rptt')
            ->where('rptt.tag = :tag')
            ->setParameter('tag', $tag);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        $qb = $this->createQueryBuilder('rpt')
            ->leftJoin('rpt.reportViewTemplateTags', 'rptt')
            ->leftJoin('rptt.tag', 't')
            ->leftJoin('t.userTags', 'tu')
            ->where('tu.publisher = :publisher')
            ->setParameter('publisher', $publisher);

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getReportViewTemplatesForUserPaginationQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('rpt.name', ':searchKey'),
                    $qb->expr()->like('rpt.id', ':searchKey')
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
                    $qb->addOrderBy('rpt.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('rpt.' . $param->getSortField(), $param->getSortDirection());
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
        return $user instanceof PublisherInterface ? $this->getReportViewTemplateForPublisherQuery($user) : $this->createQueryBuilder('rpt');
    }

    /**
     * @inheritdoc
     */
    private function getReportViewTemplateForPublisherQuery(PublisherInterface $publisher, $limit = 10, $offset  = 0)
    {
        $qb = $this->createQueryBuilder('rpt')
            ->leftJoin('rpt.reportViewTemplateTags', 'rptt')
            ->leftJoin('rptt.tag', 't')
            ->leftJoin('t.userTags', 'ut')
            ->where('ut.publisher = :publisher')
            ->setParameter('publisher', $publisher);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }
        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }
}