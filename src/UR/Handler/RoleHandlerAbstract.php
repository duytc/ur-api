<?php

namespace UR\Handler;

use Symfony\Component\Form\FormFactoryInterface;
use UR\Form\Type\RoleSpecificFormTypeInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Exception\LogicException;
use UR\Exception\InvalidUserRoleException;

/**
 * A role handler is used to have a different handler for different user roles.
 *
 * i.e you may wish an Admin to be able to edit all entities
 * however a Publisher can only edit their entities
 */
abstract class RoleHandlerAbstract extends HandlerAbstract implements RoleHandlerInterface
{
    /**
     * @var string
     */
    protected $formType;

    /**
     * @var UserRoleInterface|null
     */
    protected $userRole;

    public function __construct(FormFactoryInterface $formFactory, $formType, $domainManager, UserRoleInterface $userRole = null)
    {
        parent::__construct($formFactory, $formType, $domainManager);

        if ($userRole) {
            $this->setUserRole($userRole);
        }
    }

    public function setUserRole(UserRoleInterface $userRole)
    {
        if (!$this->supportsRole($userRole)) {
            throw new InvalidUserRoleException();
        }

        $this->userRole = $userRole;
    }

    public function getUserRole()
    {
        if (!$this->userRole instanceof UserRoleInterface) {
            throw new LogicException('userRole is not set');
        }

        return $this->userRole;
    }

    /**
     * @inheritdoc
     */
    protected function getFormType()
    {
        // TODO: check if need set userRole for formType
        // remove when not use
        // if ($this->formType instanceof RoleSpecificFormTypeInterface) {
        //     $this->formType->setUserRole($this->getUserRole());
        // }

        return $this->formType;
    }
}