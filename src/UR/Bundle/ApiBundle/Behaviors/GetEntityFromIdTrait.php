<?php

namespace UR\Bundle\ApiBundle\Behaviors;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\ManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\ModelInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

trait GetEntityFromIdTrait
{
    /**
     * get User Due To Param Publisher
     *
     * if current user is Publisher => not check param
     *
     * if current user is Admin => check param and return found publisher
     *
     * @param Request $request
     * @param string $param default is 'publisher'
     * @return AdminInterface|PublisherInterface
     * @throws NotFoundHttpException if user is admin and publisher is not found
     */
    public function getUserDueToQueryParamPublisher(Request $request, $param = 'publisher')
    {
        $currentUser = $this->getUser();

        // notice: param "publisher" is only used if current publisher is admin
        if ($currentUser instanceof AdminInterface) {
            $publisherId = $request->query->get($param, null);

            if (null !== $publisherId) {
                /** @var PublisherManagerInterface $publisherManager */
                $publisherManager = $this->getService('ur_user.domain_manager.publisher');
                $publisher = $publisherManager->findPublisher($publisherId);

                if (!$publisher instanceof PublisherInterface) {
                    throw new NotFoundHttpException('Not found publisher id #' . $publisherId);
                }

                $user = $publisher; // find by publisher
            } else {
                $user = $currentUser; // find by admin (for all publisher)
            }
        } else {
            $user = $currentUser; // find by admin (for all publisher)
        }

        return $user;
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
     * @return object
     */
    private function getService($id)
    {
        return $this->get($id);
    }

    /**
     * get current user
     * @return UserEntityInterface
     */
    public abstract function getUser();
}