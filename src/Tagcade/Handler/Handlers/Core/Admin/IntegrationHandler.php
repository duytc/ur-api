<?php

namespace Tagcade\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use Tagcade\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use Tagcade\Form\Type\RoleSpecificFormTypeInterface;
use Tagcade\Handler\Handlers\Core\IntegrationHandlerAbstract;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\AdminInterface;
use Tagcade\Model\User\Role\UserRoleInterface;

class IntegrationHandler extends IntegrationHandlerAbstract
{
    /** @var PublisherManagerInterface */
    private $publisherManager;

    /**
     * @param FormFactoryInterface $formFactory
     * @param RoleSpecificFormTypeInterface $formType
     * @param $domainManager
     * @param PublisherManagerInterface $publisherManager
     */
    function __construct(FormFactoryInterface $formFactory, RoleSpecificFormTypeInterface $formType, $domainManager, PublisherManagerInterface $publisherManager)
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

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $integration, array $parameters, $method = "PUT")
    {
        return parent::processForm($integration, $parameters, $method);
    }

}