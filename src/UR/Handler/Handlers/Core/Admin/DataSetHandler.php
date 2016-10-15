<?php

namespace UR\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Form\Type\RoleSpecificFormTypeInterface;
use UR\Handler\Handlers\Core\DataSetHandlerAbstract;
use UR\Model\Core\DataSetInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class DataSetHandler extends DataSetHandlerAbstract
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
    protected function processForm(ModelInterface $dataSet, array $parameters, $method = "PUT")
    {
        /** @var DataSetInterface $dataSet */
        if (null == $dataSet->getPublisher()) {
            if (!array_key_exists('publisher', $parameters)) {
                throw new \Exception('Expect publisher key in the $parameters array');
            }

            $publisherId = $parameters['publisher'];
            $publisher = $this->getPublisher($publisherId);

            if (!$publisher instanceof PublisherInterface) {
                throw new \Exception(sprintf('Not found publisher %d to for the DataSet', $publisherId));
            }

            $dataSet->setPublisher($publisher);
        }

        return parent::processForm($dataSet, $parameters, $method);
    }

    protected function getPublisher($publisherId)
    {
        return $this->publisherManager->findPublisher($publisherId);
    }
}