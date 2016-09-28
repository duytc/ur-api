<?php

namespace Tagcade\Handler\Handlers\Core\Publisher;


use Tagcade\Exception\LogicException;
use Tagcade\Handler\Handlers\Core\DataSourceHandlerAbstract;
use Tagcade\Model\Core\DataSourceInterface;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Model\User\Role\UserRoleInterface;

class DataSourceHandler extends DataSourceHandlerAbstract
{
    /**
     * @inheritdoc
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

    public function all($limit = null, $offset = null)
    {
        return $this->getDomainManager()->getDataSourceForPublisher($this->getUserRole(), $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    protected function processForm(ModelInterface $dataSource, array $parameters, $method = "PUT")
    {
        /** @var DataSourceInterface $dataSource */
        if (null == $dataSource->getPublisher()) {
            $dataSource->setPublisher($this->getUserRole());
        }

        return parent::processForm($dataSource, $parameters, $method);
    }
}