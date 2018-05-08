<?php

namespace UR\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Form\Type\RoleSpecificFormTypeInterface;
use UR\Handler\Handlers\Core\ReportViewTemplateTagHandlerAbstract;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\UserRoleInterface;

class ReportViewTemplateTagHandler extends ReportViewTemplateTagHandlerAbstract
{
    /** @var PublisherManagerInterface */
    private $publisherManager;

    /**
     * @param FormFactoryInterface $formFactory
     * @param string $formType
     * @param $domainManager
     * @param PublisherManagerInterface $publisherManager
     */
    function __construct(FormFactoryInterface $formFactory, $formType, $domainManager, PublisherManagerInterface $publisherManager)
    {
        parent:: __construct($formFactory, $formType, $domainManager, $userRole = null);

        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof AdminInterface;
    }
}