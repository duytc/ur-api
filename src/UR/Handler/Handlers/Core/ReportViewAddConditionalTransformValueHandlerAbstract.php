<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\ReportViewAddConditionalTransformValueManagerInterface;
use UR\Handler\RoleHandlerAbstract;
use UR\Model\User\Role\UserRoleInterface;

abstract class ReportViewAddConditionalTransformValueHandlerAbstract extends RoleHandlerAbstract
{
	/**
	 * @return ReportViewAddConditionalTransformValueManagerInterface
	 */
	protected function getDomainManager()
	{
		return parent::getDomainManager();
	}

}