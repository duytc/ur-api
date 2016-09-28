<?php

namespace Tagcade\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use Tagcade\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use Tagcade\Form\Type\RoleSpecificFormTypeInterface;
use Tagcade\Handler\Handlers\Core\DataSourceHandlerAbstract;
use Tagcade\Model\Core\DataSourceInterface;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\AdminInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Model\User\Role\UserRoleInterface;

class DataSourceHandler extends DataSourceHandlerAbstract
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