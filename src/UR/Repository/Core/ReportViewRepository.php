<?php

namespace UR\Repository\Core;

use DateTime;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSetInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class ReportViewRepository extends EntityRepository implements ReportViewRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];

    /**
     * @inheritdoc
     */
    public function getReportViewsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('rv')
            ->where('rv.publisher = :publisherId')
            ->setParameter('publisherId', $publisherId, Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }
        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }

    private function createQueryBuilderForUser($user)
    {
        return $user instanceof PublisherInterface ? $this->getReportViewsForPublisherQuery($user) : $this->createQueryBuilder('rv');
    }

    /**
     * @inheritdoc
     */
    public function getReportViewsForUserPaginationQuery(UserRoleInterface $user, PagerParam $param, $multiView = null)
    {
        if ($multiView === 'true') {
            $qb = $this->getDataSourceHasMultiViewForUserQuery($user);
        } else if ($multiView === 'false') {
            $qb = $this->getDataSourceHasNotMultiViewForUserQuery($user);
        } else {
            $qb = $this->createQueryBuilderForUser($user);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('rv.name', ':searchKey'),
                    $qb->expr()->like('rv.id', ':searchKey')
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
                    $qb->addOrderBy('rv.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('rv.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    public function getDataSourceHasMultiViewForUserQuery(UserRoleInterface $user)
    {
        $qb = $this->createQueryBuilderForUser($user);
        $qb->andWhere('rv.multiView = 1');

        return $qb;
    }

    public function getDataSourceHasMultiViewForUser(UserRoleInterface $user)
    {
        $qb = $this->getDataSourceHasMultiViewForUserQuery($user);

        return $qb->getQuery()->getResult();
    }

    public function getDataSourceHasNotMultiViewForUserQuery(UserRoleInterface $user)
    {
        $qb = $this->createQueryBuilderForUser($user);
        $qb->andWhere('rv.multiView = 0');

        return $qb;
    }

    public function getDataSourceHasNotMultiViewForUser(UserRoleInterface $user)
    {
        $qb = $this->getDataSourceHasNotMultiViewForUserQuery($user);

        return $qb->getQuery()->getResult();
    }

    public function getMultiViewReportForPublisher(PublisherInterface $publisher)
    {
        $qb = $this->createQueryBuilderForUser($publisher);
        $qb->andWhere('rv.multiView = 1');

        return $qb->getQuery()->getResult();
    }

    public function getReportViewHasMaximumId()
    {
        $qb = $this->createQueryBuilderForUser(null);
        $qb->select('rv, MAX(rv.id) as idMax');

        return $qb->getQuery()->getSingleResult();
    }

    public function getReportViewThatUseDataSet(DataSetInterface $dataSet)
    {
        return $this->createQueryBuilder('rv')
            ->join('rv.reportViewDataSets', 'rvds')
            ->where('rvds.dataSet = :dataSet')
            ->setParameter('dataSet', $dataSet)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param $reportViewId
     */
    public function updateLastRun($reportViewId)
    {
        /**
         * I wish to use native SQL to avoid doctrine events, because they would trigger many unneeded actions.
         */
        $conn = $this->_em->getConnection();
        $time = new DateTime();
        $updateSQL = sprintf('UPDATE `core_report_view` SET last_run = "%s" WHERE `id` = %s', $time->format('Y-m-d H:i:s'), (int) $reportViewId);
        try {
            $conn->exec($updateSQL);
        } catch (\Exception $e) {

        }
    }
}