<?php

namespace Tagcade\Repository\Core;


use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Tagcade\Model\User\Role\PublisherInterface;

class DataSourceIntegrationRepository extends EntityRepository implements DataSourceIntegrationRepositoryInterface
{
}