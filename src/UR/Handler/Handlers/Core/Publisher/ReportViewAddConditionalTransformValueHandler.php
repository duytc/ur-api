<?php


namespace UR\Handler\Handlers\Core\Publisher;


use UR\Exception\LogicException;
use UR\Handler\Handlers\Core\ReportViewAddConditionalTransformValueHandlerAbstract;
use UR\Model\Core\ReportViewAddConditionalTransformValueInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class ReportViewAddConditionalTransformValueHandler extends ReportViewAddConditionalTransformValueHandlerAbstract
{
    /**
     * @param UserRoleInterface $role
     * @return bool
     */
    public function supportsRole(UserRoleInterface $role)
    {
        return $role instanceof PublisherInterface;
    }

    /**
     * @inheritdoc
     * @return PublisherInterface
     * @throws LogicException
     */
    public function getUserRole()
    {
        $role = parent::getUserRole();

        if (!$role instanceof PublisherInterface) {
            throw new LogicException('userRole does not implement PublisherInterface');
        }

        return $role;
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $reportViewAddConditionalTransformValue, array $parameters, $method = "PUT")
    {
        /** @var ReportViewAddConditionalTransformValueInterface $reportViewAddConditionalTransformValue */
        if (null == $reportViewAddConditionalTransformValue->getPublisher()) {
            $reportViewAddConditionalTransformValue->setPublisher($this->getUserRole());
        }

        return parent::processForm($reportViewAddConditionalTransformValue, $parameters, $method);
    }
}