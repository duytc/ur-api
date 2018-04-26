<?php


namespace UR\Handler\Handlers\Core\Publisher;


use Symfony\Component\Form\Exception\LogicException;
use UR\Handler\Handlers\Core\OptimizationIntegrationHandlerAbstract;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class OptimizationIntegrationHandler extends OptimizationIntegrationHandlerAbstract
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
        return $this->getDomainManager()->all($limit, $offset);
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $optimizationIntegration, array $parameters, $method = "PUT")
    {
        /** @var OptimizationIntegrationInterface $optimizationIntegration */
        return parent::processForm($optimizationIntegration, $parameters, $method);
    }
}