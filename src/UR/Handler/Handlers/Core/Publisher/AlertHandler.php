<?php

namespace UR\Handler\Handlers\Core\Publisher;


use UR\Exception\LogicException;
use UR\Handler\Handlers\Core\AlertHandlerAbstract;
use UR\Model\Core\AlertInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class AlertHandler extends AlertHandlerAbstract
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
    protected function processForm(ModelInterface $reportSchedule, array $parameters, $method = "PUT")
    {
        /** @var AlertInterface $reportSchedule */
        return parent::processForm($reportSchedule, $parameters, $method);
    }
}