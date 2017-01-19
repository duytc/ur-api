<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Exception\RuntimeException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;

/**
 * @Rest\RouteResource("ReportView")
 */
class ReportViewController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all report views
     *
     * @Rest\View(serializerGroups={"report_view.summary", "user.summary", "report_view_data_set.summary", "report_view_multi_view.summary", "dataset.summary"})
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
        $publisher = $this->getUser();

        $multiView = $request->query->get('multiView', null);
        if (!is_null($multiView) && $multiView !== 'true' && $multiView !== 'false') {
            throw new \Exception('Multi view param is not valid');
        }

        $reportViewManager = $this->get('ur.domain_manager.report_view');
        $qb = $reportViewManager->getReportViewsForUserPaginationQuery($publisher, $this->getParams(), $multiView);

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single report view group for the given id
     *
     * @Rest\View(serializerGroups={"report_view.detail", "user.summary", "report_view_data_set.summary", "report_view_multi_view.summary", "dataset.summary"})
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
     * Generate shareable link for ReportView
     *
     * @Rest\Get("/reportviews/{id}/shareablelink" )
     *
     * @Rest\QueryParam(name="fields", nullable=false, description="fields to be showed in sharedReport, must match dimensions, metrics fields in ReportView")
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
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);

        $fieldsToBeShared = $request->query->get('fields', '[]');
        $fieldsToBeShared = json_decode($fieldsToBeShared);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fieldsToBeShared)) {
            throw new BadRequestHttpException('expected "fields" param is array that match fields in ReportView');
        }

        return $this->getShareableLink($reportView, $fieldsToBeShared);
    }

    /**
     * Create a report view from the submitted data
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
        return $this->postAndReturnEntityData($request);
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
     *
     * @param $id
     * @return mixed
     */
    public function postCloneAction(Request $request, $id)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);
        $cloneSettings = $request->get('cloneSettings');

        $reportViewService = $this->get('ur.service.report.clone.report');
        if (!is_array($cloneSettings)) {
            throw new Exception('cloneSettings must be array');
        }

        $reportViewService->cloneReportView($reportView, $cloneSettings);
        return '';
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
        return $this->patch($request, $id);
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
     * @return mixed
     */
    private function getShareableLink(ReportViewInterface $reportView, array $fieldsToBeShared)
    {
        /** @var ReportViewManagerInterface $reportViewManager */
        $reportViewManager = $this->get('ur.domain_manager.report_view');
        $token = $reportViewManager->createTokenForReportView($reportView, $fieldsToBeShared);

        $sharedReportViewLinkTemplate = $this->container->getParameter('shared_report_view_link');
        if (strpos($sharedReportViewLinkTemplate, '$$REPORT_VIEW_ID$$') < 0 || strpos($sharedReportViewLinkTemplate, '$$SHARED_KEY$$') < 0) {
            throw new RuntimeException('Missing server parameter key $$REPORT_VIEW_ID$$ or $$SHARED_KEY$$');
        }

        $sharedLink = $sharedReportViewLinkTemplate;
        $sharedLink = str_replace('$$REPORT_VIEW_ID$$', $reportView->getId(), $sharedLink);
        $sharedLink = str_replace('$$SHARED_KEY$$', $token, $sharedLink);

        return $sharedLink;
    }
}