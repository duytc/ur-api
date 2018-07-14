<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use \Exception;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;
use UR\Bundle\ReportApiBundle\Behaviors\DashBoardUtilTrait;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Exception\RuntimeException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\ColumnUtilTrait;
use UR\Service\PublicSimpleException;
use UR\Service\ReportViewTemplate\DTO\CustomTemplateParams;
use UR\Service\StringUtilTrait;

/**
 * @Rest\RouteResource("ReportView")
 */
class ReportViewController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;
    use ColumnUtilTrait;
    use StringUtilTrait;
    use DashBoardUtilTrait;

    /**
     * Get all report views
     *
     * @Rest\View(serializerGroups={"report_view.summary", "user.summary", "report_view_data_set.summary", "dataset.inreportview"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @Rest\QueryParam(name="multiView", nullable=true, description="value of multi view")
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return ReportViewInterface[]
     * @throws \Exception
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $multiView = $request->query->get('multiView', null);
        if (!is_null($multiView) && $multiView !== 'true' && $multiView !== 'false') {
            throw new \Exception('Multi view param is not valid');
        }

        $reportViewManager = $this->get('ur.domain_manager.report_view');
        $qb = $reportViewManager->getReportViewsForUserPaginationQuery($user, $this->getParams(), $multiView);

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * @Rest\View(serializerGroups={"datasource.missingdate", "dataSourceIntegration.summary", "user.summary"})
     *
     * @Rest\QueryParam(name="dataSets", nullable=true, description="the publisher id")
     * @Rest\QueryParam(name="reportViews", nullable=true, description="the page to get")
     * @param Request $request
     * @return mixed
     */
    public function cgetDatasourcesAction(Request $request)
    {
        $dataSetIds = $request->query->get('dataSets', null);
        $reportViewIds = $request->query->get('reportViews', null);

        if ($dataSetIds == null && $reportViewIds == null) {
            throw new NotFoundHttpException('either "dataSets" or "reportViews" is empty');
        }

        if (is_string($dataSetIds) && !empty($dataSetIds)) {
            $ids = explode(',', $dataSetIds);
            return $this->get('ur.repository.data_source')->getBrokenDateRangeDataSourceForDataSets($ids);
        }

        if (is_string($reportViewIds) && !empty($reportViewIds)) {
            $ids = explode(',', $reportViewIds);
            $dataSets = $this->get('ur.repository.report_view_data_set')->getDataSetsForReportViews($ids);
            $ids = array_map(function (ReportViewDataSetInterface $reportViewDataSet) {
                return $reportViewDataSet->getDataSet()->getId();
            }, $dataSets);
            return $this->get('ur.repository.data_source')->getBrokenDateRangeDataSourceForDataSets($ids);
        }

        throw new NotFoundHttpException('either "dataSets" or "reportViews" is invalid');
    }

    /**
     * @Rest\View(serializerGroups={"dataset.edit", "report_view.summary", "report_view_data_set.summary"})
     *
     * @Rest\QueryParam(name="dataSets", nullable=true, description="the dataSet id")
     * @Rest\QueryParam(name="reportViews", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="showDataSetName", nullable=true, description="the page to get")
     * @param Request $request
     * @return mixed
     */
    public function cgetDatasetsAction(Request $request)
    {
        $dataSetIds = $request->query->get('dataSets', null);
        $reportViewIds = $request->query->get('reportViews', null);
        $showDataSetName = $request->query->get('showDataSetName', null);
        $showDataSetName = filter_var($showDataSetName, FILTER_VALIDATE_BOOLEAN);

        if ($dataSetIds == null && $reportViewIds == null) {
            throw new NotFoundHttpException('either "dataSets" or "reportViews" is empty');
        }

        if (is_string($dataSetIds) && !empty($dataSetIds)) {
            $ids = explode(',', $dataSetIds);
            $dataSets = $this->get('ur.repository.data_set')->getDataSetByIds($ids);
            $columns = [];
            /**
             * @var DataSetInterface $dataSet
             */
            foreach ($dataSets as $dataSet) {
                foreach ($dataSet->getDimensions() as $dimension => $type) {
                    if ($showDataSetName) {
                        $dimension = sprintf('%s_%d', $dimension, $dataSet->getId());
                    }

                    $columns[$dimension] = $this->convertColumnForDataSet($dimension, $showDataSetName);
                }

                foreach ($dataSet->getMetrics() as $metric => $type) {
                    if ($showDataSetName) {
                        $metric = sprintf('%s_%d', $metric, $dataSet->getId());
                    }
                    $columns[$metric] = $this->convertColumnForDataSet($metric, $showDataSetName);
                }
            }

            return array(
                'dataSets' => $dataSets,
                'columns' => $columns
            );
        }

        if (is_string($reportViewIds) && !empty($reportViewIds)) {
            $ids = explode(',', $reportViewIds);
            $reportViews = $this->get('ur.repository.report_view')->getReportViewByIds($ids);
            $columns = [];
            /**
             * @var ReportViewInterface $reportView
             */
            foreach ($reportViews as $reportView) {
                $newFields = $this->getNewFieldsFromTransforms($reportView->getTransforms());
                $metrics = $reportView->getMetrics();
                $metrics = array_diff($metrics, $newFields);
                $reportView->setMetrics($metrics);

                foreach ($reportView->getDimensions() as $dimension) {
                    $columns[$dimension] = $this->convertColumn($dimension, $showDataSetName);
                }

                foreach ($reportView->getMetrics() as $metric) {
                    $columns[$metric] = $this->convertColumn($metric, $showDataSetName);
                }
            }

            return array(
                'reportViews' => $reportViews,
                'columns' => $columns
            );
        }

        throw new NotFoundHttpException('either "dataSets" or "reportViews" is invalid');
    }

    /**
     * Get all valid report views for ur dashboard
     *
     * @Rest\Get("/reportviews/dashboard" )
     *
     * @Rest\View(serializerGroups={"report_view.summary", "user.summary", "report_view_data_set.summary", "dataset.summary"})
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return ReportViewInterface[]
     * @throws \Exception
     */
    public function getAllValidReportViewsForDashboardAction(Request $request)
    {
        $user = $this->getUser();

        /* get all report views */
        /** @var ReportViewInterface[] $reportViews */
        $reportViews = ($user instanceof PublisherInterface)
            ? $this->get('ur.domain_manager.report_view')
                ->getReportViewsForPublisherQuery($user)
                ->getQuery()->getResult()
            : $this->all();

        /* filter all valid report views */
        $validReportViews = [];
        foreach ($reportViews as $reportView) {
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            try {
                $this->validateReportViewForDashboard($reportView);
                $validReportViews[] = $reportView;
            } catch (Exception $e) {
                // report view not satisfy => skip
            }
        }

        /* sort by last run */
        usort($validReportViews, function ($rv1, $rv2) {
            /** @var ReportViewInterface $rv1 */
            /** @var ReportViewInterface $rv2 */
            if ($rv1->getLastRun() === $rv2->getLastRun()) {
                return 0;
            }

            return ($rv1->getLastRun() > $rv2->getLastRun()) ? -1 : 1;
        });

        return $validReportViews;
    }

    /**
     * Get a single report view group for the given id
     *
     * @Rest\Get("/reportviews/{id}", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"report_view.detail", "user.summary", "report_view_data_set.summary", "dataset.inreportview"})
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return ReportViewInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get a editable state for report views
     *
     * @Rest\Get("/reportviews/editable")
     * @Rest\QueryParam(name="ids", nullable=false, description="report view ids")
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return array
     *
     */
    public function getEditableAction(Request $request)
    {
        $reportViewIdsString = $request->query->get('ids', null);
        if (empty($reportViewIdsString)) {
            throw new BadRequestHttpException('Invalid ids, expected array');
        }

        $reportViewIds = explode(',', $reportViewIdsString);
        $optimizationRuleRepository = $this->get('ur.repository.optimization_rule');
        $editable = [];
        foreach ($reportViewIds as $reportViewId) {
            $reportView = $this->one($reportViewId);
            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $optimizationRules = $optimizationRuleRepository->getOptimizationRulesForReportView($reportView);
            $editable[$reportViewId] = count($optimizationRules) == 0;
        }

        return $editable;
    }

    /**
     * Generate shareable link for ReportView
     *
     * @Rest\Post("/reportviews/{id}/shareablelink" )
     *
     *
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters={
     *      {"name"="fields", "dataType"="string", "required"=false, "description"="fields to be showed in sharedReport, must match dimensions, metrics fields in ReportView"}
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return string
     */
    public function createShareableLinkAction(Request $request, $id)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);

        $fieldsToBeShared = $request->request->get(ReportViewInterface::SHARE_FIELDS, '[]');
        $filterToBeShared = $request->request->get('filters', '[]');
        if (!is_array($fieldsToBeShared)) {
            throw new InvalidArgumentException('expect "fields" to be an array');
        }

        $dateRange = $request->request->get(ReportViewInterface::SHARE_DATE_RANGE, null);
        $allowDatesOutside = $request->request->get(ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE, false);

        return $this->getShareableLink($reportView, $fieldsToBeShared, $dateRange, $allowDatesOutside, $filterToBeShared);
    }

    /**
     * Generate shareable link for ReportView
     *
     * @Rest\Post("/reportviews/{id}/reportviewtemplates" )
     *
     *
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters={
     *      {"name"="name", "dataType"="string", "required"=false, "description"="Name of report view template want to be created. Use default report view name if null"},
     *      {"name"="tags", "dataType"="array", "required"=false, "description"="List of tags config to report view template"},
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return string
     */
    public function createReportViewTemplateAction(Request $request, $id)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);

        $customParams = new CustomTemplateParams();
        $customParams->setName($request->request->get('name', $reportView->getName()));
        $customParams->setTags($request->request->get('tags', []));

        $this->get('ur.service.report_view_template.report_view_template_service')->createReportViewTemplateFromReportView($reportView, $customParams);
    }

    /**
     * get shareable link for ReportView from given token
     *
     * @Rest\Get("/reportviews/{id}/shareablelink" )
     *
     * @Rest\QueryParam(name="token", nullable=false, description="the existing token of a shareable link")
     *
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     *  parameters={
     *      {"name"="fields", "dataType"="string", "required"=false, "description"="fields to be showed in sharedReport, must match dimensions, metrics fields in ReportView"}
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return string
     */
    public function getShareableLinkAction(Request $request, $id)
    {
        $token = $request->query->get(ReportViewInterface::TOKEN, null);
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);
        $configs = $reportView->getSharedKeysConfig();
        if (is_array($configs) && count($configs) > 0) {
            foreach ($configs as $key => $config) {
                if ($token == $key) {
                    return $this->getShareableLinkFromTemplate($reportView->getId(), $token);
                }
            }

            throw new NotFoundHttpException('Invalid token');
        }

        throw new NotFoundHttpException('Invalid token');
    }

    /**
     * Get shareable link belong to a ReportView
     *
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @return string
     */
    public function getSharekeyconfigsAction($id)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);
        $result = [];
        foreach ($reportView->getSharedKeysConfig() as $key => $value) {
            $result[] = [
                ReportViewInterface::TOKEN => $key,
                ReportViewInterface::SHARE_FIELDS => $value,
                ReportViewInterface::LINK => $this->getShareableLinkFromTemplate($reportView->getId(), $key),
            ];
        }
        return $result;
    }

    /**
     * Delete shareable link via token
     *
     * @Rest\QueryParam(name="token", nullable=true, description="token of sharable link")
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @param Request $request
     * @return string
     */
    public function cgetRevokesharekeyAction($id, Request $request)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);
        $token = $request->query->get(ReportViewInterface::TOKEN);

        $sharedKeysConfig = $reportView->getSharedKeysConfig();
        unset($sharedKeysConfig[$token]);
        $reportView->setSharedKeysConfig($sharedKeysConfig);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($reportView);
        $entityManager->flush();
    }

    /**
     * Create a report view from the submitted data
     * @Rest\View(serializerGroups={"report_view.detail", "user.summary", "report_view_data_set.summary", "dataset.inreportview"})
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     *
     * @return FormTypeInterface|View
     */
    public function postAction(Request $request)
    {
        $view = $this->post($request);
        $statusCode = $view->getStatusCode();
        if (!in_array($statusCode, [200, 201])) {
            return $view;
        }

        $routeParameters = $view->getRouteParameters();
        if (!array_key_exists('id', $routeParameters)) {
            return $view;
        }

        $id = $routeParameters['id'];

        return $this->getAction($id);
    }

    /**
     * Clone
     *
     * @Rest\Post("/reportviews/{id}/clone")
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     * @param int $id
     * @return mixed
     * @throws Exception
     */
    public function postCloneAction(Request $request, $id)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);
        $cloneSettings = $request->get('cloneSettings');

        $reportViewService = $this->get('ur.service.report.clone_report_view');
        if (!is_array($cloneSettings)) {
            throw new Exception('cloneSettings must be array');
        }

        $reportViewService->cloneReportView($reportView, $cloneSettings);
        return '';
    }

    /**
     * Create new shareableLink or modify exist shareable link for a reportView
     *
     * @Rest\Post("/reportviews/{id}/share")
     *
     * @param Request $request
     * @param $id
     *
     * @return mixed
     * @throws Exception
     */
    public function postShareableLinkAction(Request $request, $id)
    {
        /** Check permissions and find report view */
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);

        /** Get params from request */
        $token = $request->get(ReportViewInterface::TOKEN);
        $updateSharedKeyConfig = $request->get(ReportViewInterface::SHARED_KEYS_CONFIG);

        if (empty($token) || !is_array($updateSharedKeyConfig)) {
            throw new PublicSimpleException('Invalid parameters');
        }

        /** Check valid structure of share key config */
        if (!array_key_exists(ReportViewInterface::SHARE_FIELDS, $updateSharedKeyConfig) ||
            !array_key_exists(ReportViewInterface::SHARE_ALLOW_DATES_OUTSIDE, $updateSharedKeyConfig) ||
            !array_key_exists(ReportViewInterface::SHARE_DATE_RANGE, $updateSharedKeyConfig)
        ) {
            throw new PublicSimpleException('Invalid parameters');
        }

        $updateSharedKeyConfig[ReportViewInterface::SHARE_DATE_CREATED] = date_create('now', new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $existSharedKeysConfig = $reportView->getSharedKeysConfig();

        /** Update shared keys config (allow add new or modify one) */
        $sharedKeysConfigs = array_merge($existSharedKeysConfig, [$token => $updateSharedKeyConfig]);
        $reportView->setSharedKeysConfig($sharedKeysConfigs);

        $this->get('ur.domain_manager.report_view')->save($reportView);
    }

    /**
     * Update an existing report view from the submitted data or create a new report view
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      201 = "Returned when the resource is created",
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     * @param int $id the resource id
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function putAction(Request $request, $id)
    {
        return $this->put($request, $id);
    }

    /**
     * @Rest\View(serializerGroups={"report_view.detail", "user.summary", "report_view_data_set.summary", "dataset.inreportview"})
     *
     * Update an existing report view from the submitted data or create a new report view at a specific location
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     * @param int $id the resource id
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when resource not exist
     */
    public function patchAction(Request $request, $id)
    {
        $this->patch($request, $id);

        return $this->one($id);
    }

    /**
     * Delete an existing report view
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return View
     *
     * @throws NotFoundHttpException when the resource not exist
     */
    public function deleteAction($id)
    {
        return $this->delete($id);
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'reportview';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_reportview';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.report_view');
    }

    /**
     * get shareableLink for reportView, support selecting $fields To Be Shared
     *
     * @param ReportViewInterface $reportView
     * @param array $fieldsToBeShared
     * @param array $filterToBeShared
     * @param array|string|null $dateRange
     * @param bool $allowDatesOutside
     * @return mixed
     */
    private function getShareableLink(ReportViewInterface $reportView, array $fieldsToBeShared, $dateRange = null, $allowDatesOutside = false, array $filterToBeShared = [])
    {
        /** @var ReportViewManagerInterface $reportViewManager */
        $reportViewManager = $this->get('ur.domain_manager.report_view');
        $token = $reportViewManager->createTokenForReportView($reportView, $fieldsToBeShared, $dateRange, $allowDatesOutside, $filterToBeShared);

        return $this->getShareableLinkFromTemplate($reportView->getId(), $token);
    }

    /**
     * get shareableLink from template
     *
     * @param int $reportViewId
     * @param string $token
     * @param string|null $template must contain macros $$REPORT_VIEW_ID$$ and $$SHARED_KEY$$. If null => use default template from config
     * @return mixed
     */
    private function getShareableLinkFromTemplate($reportViewId, $token, $template = null)
    {
        $sharedLink = (empty($template))
            ? $this->container->getParameter('shared_report_view_link')
            : $template;

        if (strpos($sharedLink, '$$REPORT_VIEW_ID$$') < 0 || strpos($sharedLink, '$$SHARED_KEY$$') < 0) {
            throw new RuntimeException('Missing server parameter key $$REPORT_VIEW_ID$$ or $$SHARED_KEY$$');
        }

        $sharedLink = str_replace('$$REPORT_VIEW_ID$$', $reportViewId, $sharedLink);
        $sharedLink = str_replace('$$SHARED_KEY$$', $token, $sharedLink);

        return $sharedLink;
    }

    protected function getDataSetManager()
    {
        return $this->get('ur.domain_manager.data_set');
    }
}