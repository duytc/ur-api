<?php


namespace UR\Repository\Core;


use Doctrine\ORM\EntityRepository;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class AutoOptimizationConfigRepository extends EntityRepository implements AutoOptimizationConfigRepositoryInterface
{
    const TRANSFORM_TYPE_KEY = 'type';
    const ADD_CONDITION_VALUE_TRANSFORM_TYPE = 'addConditionValue';
    const ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY = 'values';
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];
    /**
     * @inheritdoc
     */
    public function removeAddConditionalTransformValue($id)
    {
        $autoOptimizationConfigs = $this->findAll();

        /** @var AutoOptimizationConfigInterface[] $autoOptimizationConfigs */
        foreach ($autoOptimizationConfigs as $autoOptimizationConfig) {
            $newTransforms = [];
            $transforms = $autoOptimizationConfig->getTransforms();

            if (is_null($transforms)) {
                continue;
            }

            foreach ($transforms as $transform) {
                //$transform = json_decode($transform, true);
                if ($transform[self::TRANSFORM_TYPE_KEY] === self::ADD_CONDITION_VALUE_TRANSFORM_TYPE) {
                    $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                    foreach ($fields as &$field) {
                        $ids = $field[self::ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY];
                        $key = array_search($id, $ids);
                        if ($key !== false) {
                            unset($ids[$key]);
                            $field[self::ADD_CONDITION_VALUE_TRANSFORM_VALUE_KEY] = array_values($ids);
                        }
                    }
                    $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                }
                $newTransforms[] = $transform;
            }
            $autoOptimizationConfig->setTransforms($newTransforms);

            $this->_em->persist($autoOptimizationConfig);
        }
        $this->_em->flush();
    }

    /**
     * @inheritdoc
     */
    public function getAutoOptimizationConfigsForUserQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('aoc.name', ':searchKey'),
                    $qb->expr()->like('aoc.id', ':searchKey')
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
                    $qb->addOrderBy('aoc.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('aoc.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }
        return $qb;
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getAutoOptimizationConfigsForPublisherQuery($user) : $this->createQueryBuilder('aoc');
    }

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher)
    {
        $qb = $this->getAutoOptimizationConfigsForPublisherQuery($publisher);

        return $qb->getQuery()->getResult();
    }
    /**
     * @inheritdoc
     */
    public function getAutoOptimizationConfigsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('aoc')
            ->where('aoc.publisher = :publisher')
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