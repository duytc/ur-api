<?php


namespace UR\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use UR\Form\Type\RoleSpecificFormTypeInterface;
use UR\Handler\Handlers\Core\OptimizationIntegrationHandlerAbstract;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\UserRoleInterface;

class OptimizationIntegrationHandler extends OptimizationIntegrationHandlerAbstract
{

    /**
     * @param FormFactoryInterface $formFactory
     * @param RoleSpecificFormTypeInterface $formType
     * @param $domainManager
     */
    function __construct(FormFactoryInterface $formFactory, RoleSpecificFormTypeInterface $formType, $domainManager)
    {
        parent:: __construct($formFactory, $formType, $domainManager, $userRole = null);
    }

    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof AdminInterface;
    }
}