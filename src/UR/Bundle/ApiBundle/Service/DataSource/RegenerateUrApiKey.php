<?php
namespace UR\Bundle\ApiBundle\Service\DataSource;

use Doctrine\ORM\EntityManager;
use UR\Behaviors\CreateUrApiKeyTrait;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;


class RegenerateUrApiKey
{
    use CreateUrApiKeyTrait;

    /**@var DataSourceManagerInterface $dataSourceManager */
    protected $dataSourceManager;
    /**
     * RegenerateEmail constructor.
     * @param DataSourceManagerInterface $dataSourceManager
     */
    public function __construct(DataSourceManagerInterface $dataSourceManager)
    {
        $this->dataSourceManager = $dataSourceManager;
    }

    public function regenerateUrApiKey($id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->dataSourceManager->find($id);
        if ($dataSource === null) {
            return false;
        }
        $isUnique = false;
        while (!$isUnique) {
            $apiKey = $this->generateUrApiKey($dataSource->getPublisher()->getUser()->getUsername());
            $entity = $this->dataSourceManager->getDataSourceByApiKey($apiKey);
            if (count($entity) === 0) {
                $isUnique = true;
                $dataSource->setApiKey($apiKey);
            }
        }

        $this->dataSourceManager->save($dataSource);
        return true;
    }
}