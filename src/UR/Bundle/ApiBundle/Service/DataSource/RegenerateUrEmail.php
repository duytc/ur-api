<?php
namespace UR\Bundle\ApiBundle\Service\DataSource;

use Doctrine\ORM\EntityManager;
use UR\Behaviors\CreateUrEmailTrait;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;


class RegenerateUrEmail
{
    use CreateUrEmailTrait;

    /**@var DataSourceManagerInterface $dataSourceManager */
    protected $dataSourceManager;

    protected $urEmailTemplate;
    /**
     * RegenerateEmail constructor.
     * @param DataSourceManagerInterface $dataSourceManager
     * @param $urEmailTemplate
     */
    public function __construct(DataSourceManagerInterface $dataSourceManager, $urEmailTemplate)
    {
        $this->dataSourceManager = $dataSourceManager;
        $this->urEmailTemplate = $urEmailTemplate;
    }

    public function regenerateUrEmail($id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->dataSourceManager->find($id);
        if ($dataSource === null) {
            return false;
        }
        $isUnique = false;
        while (!$isUnique) {
            $urEmail = $this->generateUniqueUrEmail($dataSource->getPublisherId(), $this->urEmailTemplate);
            $entity = $this->dataSourceManager->findByEmail($urEmail);
            if ($entity === null) {
                $isUnique = true;
                $dataSource->setUrEmail($urEmail);
            }
        }

        $this->dataSourceManager->save($dataSource);
        return true;
    }
}