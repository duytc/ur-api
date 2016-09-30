<?php

namespace UR\Handler\Handlers\Core\Publisher;


use UR\DomainManager\IntegrationManagerInterface;
use UR\Exception\LogicException;
use UR\Handler\Handlers\Core\IntegrationHandlerAbstract;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class IntegrationHandler extends IntegrationHandlerAbstract
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

    public function all($limit = null, $offset = null)
    {
        return $this->getDomainManager()->all($limit, $offset);
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $integration, array $parameters, $method = "PUT")
    {
        /** @var IntegrationManagerInterface $dataSource */
        return parent::processForm($integration, $parameters, $method);
    }
}