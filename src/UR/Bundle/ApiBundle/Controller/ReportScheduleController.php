<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\AlertInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\ReportScheduleInterface;
use UR\Model\Core\ReportViewInterface;

/**
 * @Rest\RouteResource("ReportSchedule")
 */
class ReportScheduleController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all report schedule
     *
     * @Rest\View(serializerGroups={"report_schedule.summary"})
     *
     * @ApiDoc(
     *  section = "ReportView",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return ReportScheduleInterface[]
     */
    public function cgetAction()
    {
        return $this->all();
    }

    /**
     * Get a single report schedule group for the given id
     *
     * @Rest\View(serializerGroups={"report_schedule.detail"})
     *
     * @ApiDoc(
     *  section = "ReportSchedule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return ReportScheduleInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a report schedule from the submitted data
     *
     * @ApiDoc(
     *  section = "ReportSchedule",
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
     * Update an existing report schedule from the submitted data or create a new report schedule
     *
     * @ApiDoc(
     *  section = "ReportSchedule",
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
     * Update an existing report schedule from the submitted data or create a new report schedule at a specific location
     *
     * @ApiDoc(
     *  section = "ReportSchedule",
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
     * Delete an existing report schedule
     *
     * @ApiDoc(
     *  section = "ReportSchedule",
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
        return 'reportschedule';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_report_schedule';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.report_schedule');
    }
}