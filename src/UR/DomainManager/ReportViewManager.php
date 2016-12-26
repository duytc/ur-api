<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
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

        if ($this->checkIfReportViewBelongsToMultiView($reportView)) {
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

    public function checkIfReportViewBelongsToMultiView(ReportViewInterface $reportView)
    {
        $reports = $this->repository->getMultiViewReportForPublisher($reportView->getPublisher());
        /**
         * @var ReportViewInterface $report
         */
        foreach($reports as $report) {
            $views = ParamsBuilder::createReportViews($report->getReportViews());
            /**
             * @var \UR\Domain\DTO\Report\ReportViews\ReportViewInterface $view
             */
            foreach($views as $view) {
                if ($view->getReportViewId() === $reportView->getId()) {
                    return true;
                }
            }
        }

        return false;
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

        $newToken = ReportView::generateToken();

        // update fields if newToken existed (very very seldom occurred)
        if (array_key_exists($newToken, $sharedKeysConfig)) {
            $sharedKeysConfig[$newToken] = $fieldsToBeShared;
        } else {
            $concatenatedFieldsToBeShared = implode(':', $fieldsToBeShared);

            // unset old token before adding new token when request same $fieldsToBeShared
            foreach ($sharedKeysConfig as $token => $fields) {
                $concatenatedFields = implode(':', $fields);

                if ($concatenatedFields === $concatenatedFieldsToBeShared) {
                    unset($sharedKeysConfig[$token]);
                }
            }

            // add new token
            $sharedKeysConfig[$newToken] = $fieldsToBeShared;
        }

        // update sharedKeysConfig
        $reportView->setSharedKeysConfig($sharedKeysConfig);

        // try to save and refresh
        try {
            $this->om->persist($reportView);
            $this->om->flush();
        } catch (\Exception $e) {
            throw new RuntimeException('Could not create new token');
        }

        return $newToken;
    }
}