<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class OptimizationRuleRepository extends EntityRepository implements OptimizationRuleRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name', 'createdDate' => 'createdDate'];

    /**
     * @inheritdoc
     */
    public function getOptimizationRulesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->getOptimizationRulesForPublisherQuery($publisher, $limit, $offset)
            ->addOrderBy('opr.name', 'asc');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationRulesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('opr')
            ->leftJoin('opr.publisher', 'p')
            ->leftJoin('opr.reportView', 'rw')
            ->where('opr.publisher = :publisherId')
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
    public function getOptimizationRulesForUserQuery(UserRoleInterface $user, PagerParam $param)
    {

        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('opr.name', ':searchKey'),
                    $qb->expr()->like('opr.id', ':searchKey')
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
                    $qb->addOrderBy('opr.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('opr.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['createdDate']:
                    $qb->addOrderBy('opr.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;

    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getOptimizationRulesForPublisherQuery($user) : $this->createQueryBuilder('opr');
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationRulesForReportView(ReportViewInterface $reportView, $limit = null, $offset = null) {

        $qb = $this->createQueryBuilder('opr')
            ->leftJoin('opr.reportView', 'rw')
            ->where('rw.id = :reportViewId')
            ->setParameter('reportViewId', $reportView->getId(), Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getOptimizationRulesForDataSet(DataSetInterface $dataSet, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('opr')
            ->leftJoin('opr.reportView', 'rv')
            ->leftJoin('rv.reportViewDataSets', 'rwd')
            ->leftJoin('rwd.dataSet', 'dt')
            ->where('dt.id = :datSetId')
            ->setParameter('datSetId', $dataSet->getId(), Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function hasReportView(ReportViewInterface $reportView)
    {
        $rules = $this->createQueryBuilder('op')
            ->where('op.reportView = :rv')
            ->setParameter('rv', $reportView)
            ->getQuery()
            ->getResult();

        return count($rules) > 0;
    }
}