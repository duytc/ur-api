<?php

namespace UR\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Form\Type\RoleSpecificFormTypeInterface;
use UR\Handler\Handlers\Core\DataSourceHandlerAbstract;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Repository\Core\IntegrationPublisherRepositoryInterface;

class DataSourceHandler extends DataSourceHandlerAbstract
{
    /** @var IntegrationPublisherRepositoryInterface */
    private $integrationPublisherManager;

    /** @var PublisherManagerInterface $publisherManager */
    private $publisherManager;

    /**
     * @param FormFactoryInterface $formFactory
     * @param RoleSpecificFormTypeInterface $formType
     * @param $domainManager
     * @param PublisherManagerInterface $publisherManager
     * @param IntegrationPublisherRepositoryInterface $integrationPublisherManager
     */
    function __construct(FormFactoryInterface $formFactory, RoleSpecificFormTypeInterface $formType, $domainManager, PublisherManagerInterface $publisherManager, IntegrationPublisherRepositoryInterface $integrationPublisherManager)
    {
        parent:: __construct($formFactory, $formType, $domainManager, $userRole = null);

        $this->publisherManager = $publisherManager;
        $this->integrationPublisherManager = $integrationPublisherManager;
    }

    /**
     * @inheritdocl
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof AdminInterface;
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $dataSource, array $parameters, $method = "PUT")
    {
        /** @var DataSourceInterface $dataSource */
        if (null == $dataSource->getPublisher()) {
            if (!array_key_exists('publisher', $parameters)) {
                throw new \Exception('Expect publisher key in the $parameters array');
            }

            $publisherId = $parameters['publisher'];
            $publisher = $this->getPublisher($publisherId);

            if (!$publisher instanceof PublisherInterface) {
                throw new \Exception(sprintf('Not found publisher %d to for the DataSource', $publisherId));
            }

            $dataSource->setPublisher($publisher);
        }

        return parent::processForm($dataSource, $parameters, $method);
    }

    protected function getPublisher($publisherId)
    {
        return $this->publisherManager->findPublisher($publisherId);
    }
}