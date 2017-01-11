<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Entity\Core\ReportViewMultiView;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Model\Core\ReportView;
use UR\Model\Core\ReportViewInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;
use UR\Service\Report\ParamsBuilder;

class ReportViewManager implements ReportViewManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, ReportViewRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ReportViewInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $reportView)
    {
        if (!$reportView instanceof ReportViewInterface) throw new InvalidArgumentException('expect ReportViewInterface object');

        try {
            $this->om->persist($reportView);
            $this->om->flush();
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $reportView)
    {
        if (!$reportView instanceof ReportViewInterface) throw new InvalidArgumentException('expect ReportViewInterface object');

        $reportViewMultiViewRepository = $this->om->getRepository(ReportViewMultiView::class);
        if ($reportViewMultiViewRepository->checkIfReportViewBelongsToMultiView($reportView)) {
            throw new InvalidArgumentException('This report view belongs to another report view');
        }

        $this->om->remove($reportView);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        $entity = new ReflectionClass($this->repository->getClassName());
        return $entity->newInstance();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->repository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }

    public function getReportViewsForUserPaginationQuery(UserRoleInterface $publisher, PagerParam $param, $multiView)
    {
        return $this->repository->getReportViewsForUserPaginationQuery($publisher, $param, $multiView);
    }

    /**
     * @inheritdoc
     */
    public function createTokenForReportView(ReportViewInterface $reportView, array $fieldsToBeShared)
    {
        $sharedKeysConfig = $reportView->getSharedKeysConfig();
        if (!is_array($sharedKeysConfig)) {
            $sharedKeysConfig = [];
        }

        // check if fieldsToBeShared already existed
        $concatenatedFieldsToBeShared = implode(':', $fieldsToBeShared);
        $tokenExisted = false;
        $newToken = '';

        foreach ($sharedKeysConfig as $token => $fields) {
            $concatenatedOldFieldsToBeShared = implode(':', $fields);

            if ($concatenatedOldFieldsToBeShared === $concatenatedFieldsToBeShared) {
                $tokenExisted = true;
                $newToken = $token; // return old token
                break;
            }
        }

        // add new token if token not existed
        if (!$tokenExisted) {
            $newToken = ReportView::generateToken();
            $sharedKeysConfig[$newToken] = $fieldsToBeShared;

            // update sharedKeysConfig
            $reportView->setSharedKeysConfig($sharedKeysConfig);

            // try to save and refresh
            try {
                $this->om->persist($reportView);
                $this->om->flush();
            } catch (\Exception $e) {
                throw new RuntimeException('Could not create new token');
            }
        }

        return $newToken;
    }
}