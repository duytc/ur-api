<?php

namespace UR\Handler\Handlers\Core\Publisher;

use UR\Handler\Handlers\Core\AdNetworkHandlerAbstract;
use UR\Model\ModelInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Exception\LogicException;
use UR\Model\Core\AdNetworkInterface;

class AdNetworkHandler extends AdNetworkHandlerAbstract
{
    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }

    /**
     * @inheritdoc
     * @return PublisherInterface
     * @throws LogicException
     */
    public function getUserRole()
    {
        $role = parent::getUserRole();

        if (!$role instanceof PublisherInterface) {
            throw new LogicException('userRole does not implement PublisherInterface');
        }

        return $role;
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->getDomainManager()->getAdNetworksForPublisher($this->getUserRole(), $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $adNetwork, array $parameters, $method = "PUT")
    {
        /** @var AdNetworkInterface $adNetwork */
        if (null == $adNetwork->getPublisher()) {
            $adNetwork->setPublisher($this->getUserRole());
        }

        return parent::processForm($adNetwork, $parameters, $method);
    }
}