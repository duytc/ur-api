<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Entity\Core\ReportViewMultiView;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Model\Core\ReportView;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;

class ReportViewManager implements ReportViewManagerInterface
{
    const CLONE_REPORT_VIEW_NAME = 'name';
    const CLONE_REPORT_VIEW_ALIAS = 'alias';
    const CLONE_REPORT_VIEW_TRANSFORM = 'transforms';
    const CLONE_REPORT_VIEW_FORMAT = 'formats';
    const CLONE_REPORT_VIEW_FILTER = 'filters';
    const CLONE_REPORT_VIEW_DATA_SET = 'dataSet';

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

    public function cloneReportView(ReportViewInterface $reportView, array $cloneSettings)
    {
        foreach ($cloneSettings as $cloneSetting) {
            $newReportView = clone $reportView;
            $newName = array_key_exists(self::CLONE_REPORT_VIEW_NAME, $cloneSetting) ? $cloneSetting[self::CLONE_REPORT_VIEW_NAME] : $reportView->getName();
            $newAlias = array_key_exists(self::CLONE_REPORT_VIEW_ALIAS, $cloneSetting) ? $cloneSetting[self::CLONE_REPORT_VIEW_ALIAS] : $cloneSetting[self::CLONE_REPORT_VIEW_NAME];
            $newReportView->setName($newName === null ? $reportView->getName() : $newName);
            $newReportView->setAlias($newAlias === null ? $newName : $newAlias);
            $newReportViewDataSetJson = [];
            if (array_key_exists('reportViewDataSets', $cloneSettings)) {
                $newReportViewDataSetJson = $cloneSetting['reportViewDataSets'];
                $newTransforms = array_key_exists(self::CLONE_REPORT_VIEW_TRANSFORM, $cloneSettings) ? $cloneSetting[self::CLONE_REPORT_VIEW_TRANSFORM] : [];
                $newFormats = array_key_exists(self::CLONE_REPORT_VIEW_FORMAT, $cloneSettings) ? $cloneSetting[self::CLONE_REPORT_VIEW_FORMAT] : [];
                $newReportView->setTransforms($newTransforms);
                $newReportView->setFormats($newFormats);
            }

            // clone filters
            /** @var ReportViewDataSetInterface[] $reportViewDataSets */
            $reportViewDataSets = $reportView->getReportViewDataSets();
            $newReportViewDataSets = [];
            foreach ($reportViewDataSets as $reportViewDataSet) {
                $newReportViewDataSet = clone $reportViewDataSet;
                $newReportViewDataSet->setReportView($newReportView);
                // process with $newReportViewDataSetJson
                foreach ($newReportViewDataSetJson as $item) {
                    if (!array_key_exists(self::CLONE_REPORT_VIEW_DATA_SET, $item)) {
                        throw new Exception('message should contains % key', self::CLONE_REPORT_VIEW_DATA_SET);
                    }

                    if ($newReportViewDataSet->getDataSet()->getId() === $item[self::CLONE_REPORT_VIEW_DATA_SET]) {
                        $newReportViewDataSet->setFilters($item['filters']);
                    }

                    continue;
                }

                $newReportViewDataSets[] = $newReportViewDataSet;
            }

            $newReportView->setReportViewDataSets($newReportViewDataSets);
            $this->save($newReportView);
        }
    }
}