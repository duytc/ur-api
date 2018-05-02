<?php


namespace UR\Handler\Handlers\Core\Publisher;


use Symfony\Component\Form\Exception\LogicException;
use UR\Handler\Handlers\Core\OptimizationHandlerAbstract;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class OptimizationRuleHandler extends OptimizationHandlerAbstract
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
        return $this->getDomainManager()->getOptimizationRulesForPublisher($this->getUserRole(), $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $optimizationRule, array $parameters, $method = "PUT")
    {
        /** @var OptimizationRuleInterface $optimizationRule */
        if (null == $optimizationRule->getPublisher()) {
            $optimizationRule->setPublisher($this->getUserRole());
        }

        /** @var OptimizationRuleInterface $optimizationRule */
        return parent::processForm($optimizationRule, $parameters, $method);
    }
}