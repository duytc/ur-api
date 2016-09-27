<?php

namespace Tagcade\Handler\Handlers\Core\Publisher;

use Tagcade\Handler\Handlers\Core\AdNetworkHandlerAbstract;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\UserRoleInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Exception\LogicException;
use Tagcade\Model\Core\AdNetworkInterface;

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