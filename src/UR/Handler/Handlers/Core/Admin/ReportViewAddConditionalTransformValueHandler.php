<?php


namespace UR\Handler\Handlers\Core\Admin;


use Symfony\Component\Form\FormFactoryInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Form\Type\RoleSpecificFormTypeInterface;
use UR\Handler\Handlers\Core\ReportViewAddConditionalTransformValueHandlerAbstract;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\UserRoleInterface;

class ReportViewAddConditionalTransformValueHandler extends ReportViewAddConditionalTransformValueHandlerAbstract
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
	 * @param UserRoleInterface $role
	 * @return bool
	 */
	public function supportsRole(UserRoleInterface $role)
	{
		return $role instanceof AdminInterface;
	}
}