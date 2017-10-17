<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;
use UR\Repository\Core\ReportViewDataSetRepositoryInterface;

class ReportViewDataSetManager implements ReportViewDataSetManagerInterface
{
    protected $om;
    protected $repository;
    const DATE_CREATED = 'dateCreated';

    public function __construct(ObjectManager $om, ReportViewDataSetRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ReportViewDataSetInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $reportView)
    {
        if (!$reportView instanceof ReportViewDataSetInterface) {
            throw new InvalidArgumentException('expect ReportViewDataSetInterface object');
        }

        try {
            $this->om->persist($reportView);
            $this->om->flush();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $reportView)
    {
        if (!$reportView instanceof ReportViewDataSetInterface) {
            throw new InvalidArgumentException('expect ReportViewDataSetInterface object');
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
    public function getReportViewsByDataSet(DataSetInterface $dataSet)
    {
        return $this->repository->getReportViewsByDataSet($dataSet);
    }

    protected function compareDateRange($source, $destination)
    {
        return md5(serialize($source)) === md5(serialize($destination));
    }
}