<?php

namespace Tagcade\Handler\Handlers\Core\Publisher;


use Tagcade\DomainManager\IntegrationGroupManagerInterface;
use Tagcade\Exception\LogicException;
use Tagcade\Handler\Handlers\Core\IntegrationGroupHandlerAbstract;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Model\User\Role\UserRoleInterface;

class IntegrationGroupHandler extends IntegrationGroupHandlerAbstract
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
    protected function processForm(ModelInterface $integrationGroup, array $parameters, $method = "PUT")
    {
        /** @var IntegrationGroupManagerInterface $dataSource */
        return parent::processForm($integrationGroup, $parameters, $method);
    }
}