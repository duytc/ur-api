<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Service\DataSet\Locator;

class ImportHistoryRepository extends EntityRepository implements ImportHistoryRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'dataSet' => 'dataSet'];

    /**
     * @inheritdoc
     */
    public function getImportHistoriesForUserPaginationQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.id', ':searchKey'),
                    $qb->expr()->like('ih.id', ':searchKey')
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
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['dataSet']:
                    $qb->addOrderBy('ih.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getDataSetsForPublisherQuery($user) : $this->createQueryBuilder('ih')->join('ih.dataSet', 'ds');
    }

    /**
     * @inheritdoc
     */
    public function getDataSetsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('ih')
            ->join('ih.dataSet', 'ds')
            ->where('ds.publisher = :publisherId')
            ->setParameter('publisherId', $publisherId, Type::INTEGER);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }
        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }

    public function getImportedDataByIdQuery($dataSetId, $importId)
    {
        $conn = $this->_em->getConnection();
        $query = "select * from __data_import_" . $dataSetId . " where __import_id = ?";
        $stmt = $conn->prepare($query);
            $stmt->bindValue(1, $importId);
        $stmt->execute();
        $results = $stmt->fetchAll();
        return $results;
    }
}