<?php

namespace UR\Bundle\ApiBundle\Behaviors;


use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\AdNetworkManagerInterface;
use UR\DomainManager\ManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\AdNetworkInterface;
use UR\Model\ModelInterface;

trait GetEntityFromIdTrait
{
    /**
     * Get ad network of id ad network array
     * @param $ids
     * @return array|\UR\Model\ModelInterface[]
     */
    protected function getAdNetworks($ids)
    {
        $myIds = $this->convertInputToArray($ids);
        /** @var AdNetworkManagerInterface $channelManager */
        $adSlotManager = $this->get('ur.domain_manager.ad_network');

        return $this->createEntitiesObject($adSlotManager, $myIds, AdNetworkInterface::class);
    }

    /**
     * create entity objects from manager and ids with expected class
     *
     * @param ManagerInterface $manager
     * @param array $ids
     * @param string $class
     * @return array|ModelInterface[]
     */
    private function createEntitiesObject(ManagerInterface $manager, array $ids, $class)
    {
        $entities = [];

        foreach ($ids as $id) {
            $entity = $manager->find($id);

            if (!is_a($entity, $class)) {
                throw new NotFoundHttpException(sprintf('entity %s with id %d is not found', $class, $id));
            }

            if (!in_array($entity, $entities)) {
                $this->checkUserPermission($entity, 'edit');
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * convert an input to array
     *
     * @param mixed $ids one or array
     * @return array
     */
    private function convertInputToArray($ids)
    {
        if (is_numeric($ids) && $ids < 1) {
            throw new InvalidArgumentException('Expect a positive integer or array');
        }

        return !is_array($ids) ? [$ids] : $ids;
    }

    /**
     * check user permission for an entity
     *
     * @param ModelInterface $entity
     * @param string $permission
     * @return mixed
     */
    protected abstract function checkUserPermission($entity, $permission = 'view');

    /**
     * Get service instance, this should be called in a controller or a container-aware service which has container to get a service by id
     *
     * @param $id
     * @return mixed
     */
    public abstract function get($id);
}