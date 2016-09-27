<?php

namespace Tagcade\Handler\Handlers\Core\Admin;

use Symfony\Component\Form\FormFactoryInterface;
use Tagcade\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use Tagcade\Form\Type\RoleSpecificFormTypeInterface;
use Tagcade\Handler\Handlers\Core\AdNetworkHandlerAbstract;
use Tagcade\Model\Core\AdNetworkInterface;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\AdminInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Model\User\Role\UserRoleInterface;

class AdNetworkHandler extends AdNetworkHandlerAbstract
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
    protected function processForm(ModelInterface $adNetwork, array $parameters, $method = "PUT")
    {
        /** @var AdNetworkInterface $adNetwork */
        if (null == $adNetwork->getPublisher()) {
            if (!array_key_exists('publisher', $parameters)) {
                throw new \Exception('Expect publisher key in the $parameters array');
            }

            $publisherId = $parameters['publisher'];
            $publisher = $this->getPublisher($publisherId);

            if (!$publisher instanceof PublisherInterface) {
                throw new \Exception(sprintf('Not found publisher %d to for the ad network', $publisherId));
            }

            $adNetwork->setPublisher($publisher);
        }

        return parent::processForm($adNetwork, $parameters, $method);
    }

    protected function getPublisher($publisherId)
    {
        return $this->publisherManager->findPublisher($publisherId);
    }
}