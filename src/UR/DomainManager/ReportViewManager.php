<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportView;
use UR\Model\Core\ReportViewInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;

class ReportViewManager implements ReportViewManagerInterface
{
    protected $om;
    protected $repository;
    const DATE_CREATED = 'dateCreated';

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
        if (!$reportView instanceof ReportViewInterface) {
            throw new InvalidArgumentException('expect ReportViewInterface object');
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
        if (!$reportView instanceof ReportViewInterface) {
            throw new InvalidArgumentException('expect ReportViewInterface object');
        }

        if ($this->repository->hasSubviews($reportView)) {
            throw new InvalidArgumentException("There're some subviews still referencing to this report view");
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
    public function createTokenForReportView(ReportViewInterface $reportView, array $fieldsToBeShared, $dateRange = null, $allowDatesOutside = false)
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
            $concatenatedOldFieldsToBeShared = implode(':', $fields[ReportViewInterface::SHARE_FIELDS]);

            // check if token existed base on:
            // - the shared fields
            // - the shared date range
            // - the shared option allowDatesOutside
            if (
                $concatenatedOldFieldsToBeShared === $concatenatedFieldsToBeShared &&
                (
                    (array_key_exists(ReportViewInterface::SHARE_DATE_RANGE, $fields) && $this->compareDateRange($fields[ReportViewInterface::SHARE_DATE_RANGE], $dateRange)) ||
                    (array_key_exists(ReportViewInterface::SHARE_DATE_RANGE, $fields) && $dateRange === null)
                ) &&
                (
                    array_key_exists(ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE, $fields) && $fields[ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE] == $allowDatesOutside
                )
            ) {
                $tokenExisted = true;
                $newToken = $token; // return old token
                break;
            }
        }

        // add new token if token not existed
        if (!$tokenExisted) {
            $newToken = ReportView::generateToken();
            $sharedKeysConfig[$newToken] = array(
                ReportViewInterface::SHARE_FIELDS => $fieldsToBeShared,
                ReportViewInterface::SHARE_DATE_RANGE => $dateRange,
                ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE => $allowDatesOutside,
                self::DATE_CREATED => date("Y-m-d H:i:s")
            );

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

    public function getSingleViews()
    {
        return $this->repository->getSingleViews();
    }
}