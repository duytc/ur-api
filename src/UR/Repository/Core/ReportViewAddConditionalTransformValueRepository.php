<?php

namespace UR\Repository\Core;


use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class ReportViewAddConditionalTransformValueRepository extends EntityRepository implements ReportViewAddConditionalTransformValueRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name', 'createDate' => 'createDate', 'defaultValue' => 'defaultValue'];

    /**
     * @inheritdoc
     */
    public function getReportViewAddConditionalTransformValueForPubQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('rvactv')
            ->where('rvactv.publisher = :publisherId')
            ->setParameter('publisherId', $publisherId, Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getReportViewAddConditionalTransformValueQuery(UserRoleInterface $user, array $ids, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (!empty($ids)) {
            $qb
                ->andWhere('rvactv.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('rvactv.name', ':searchKey'),
                    $qb->expr()->like('rvactv.id', ':searchKey'),
                    $qb->expr()->like('rvactv.createDate', ':searchKey'),
                    $qb->expr()->like('rvactv.defaultValue', ':searchKey')
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
                    $qb->addOrderBy('rvactv.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('rvactv.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['createDate']:
                    $qb->addOrderBy('rvactv.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['defaultValue']:
                    $qb->addOrderBy('rvactv.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getReportViewAddConditionalTransformValueForPubQuery($user) : $this->createQueryBuilder('rvactv');
    }
}