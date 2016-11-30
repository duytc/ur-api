<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Exception\RuntimeException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\ReportViewInterface;

/**
 * @Rest\RouteResource("ReportView")
 */
class ReportViewController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all report views
     *
     * @Rest\View(serializerGroups={"report_view.summary", "user.summary"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
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
     */
    public function cgetAction(Request $request)
    {
        $publisher = $this->getUser();

        $reportViewManager = $this->get('ur.domain_manager.report_view');
        $qb = $reportViewManager->getReportViewsForUserPaginationQuery($publisher, $this->getParams());

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
     * @Rest\View(serializerGroups={"report_view.detail", "user.summary"})
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
     * @ApiDoc(
     *  section = "Report View",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return string
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getShareableLinkAction($id)
    {
        /** @var ReportViewInterface $reportView */
        $reportView = $this->one($id);

        return $this->getShareableLink($reportView);
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
        return $this->post($request);
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

    private function getShareableLink(ReportViewInterface $reportView)
    {
        $sharedReportViewLinkTemplate = $this->container->getParameter('shared_report_view_link');
        if (strpos($sharedReportViewLinkTemplate, '$$REPORT_VIEW_ID$$') < 0 || strpos($sharedReportViewLinkTemplate, '$$SHARED_KEY$$') < 0) {
            throw new RuntimeException('Missing server parameter key $$REPORT_VIEW_ID$$ or $$SHARED_KEY$$');
        }

        $sharedKey = $reportView->getSharedKey();

        $sharedLink = $sharedReportViewLinkTemplate;
        $sharedLink = str_replace('$$REPORT_VIEW_ID$$', $reportView->getId(), $sharedLink);
        $sharedLink = str_replace('$$SHARED_KEY$$', $sharedKey, $sharedLink);

        return $sharedLink;
    }
}