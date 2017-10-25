<?php

namespace UR\Bundle\ApiBundle\Controller;


use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;


/**
 * @Rest\RouteResource("ReportViewAddConditionalTransformValue")
 */
class ReportViewAddConditionalTransformValueController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

	/**
	 * Create a report view add conditional transform value from the submitted data
	 *
	 * @ApiDoc(
	 *  section = "ReportViewAddConditionalTransformValue",
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
	 * Get a single report view add conditional transform value for the given id
	 *
	 * @Rest\View(serializerGroups={"report_view_add_conditional_transform_value.detail", "user.summary"})
	 *
	 * @ApiDoc(
	 *  section = "ReportViewAddConditionalTransformValue",
	 *  resource = true,
	 *  statusCodes = {
	 *      200 = "Returned when successful"
	 *  }
	 * )
	 *
	 * @param int $id the resource id
	 *
	 * @return \UR\Model\Core\ReportViewAddConditionalTransformValueInterface
	 * @throws NotFoundHttpException when the resource does not exist
	 */
	public function getAction($id)
	{
		return $this->one($id);
	}

	/**
	 * Get a list report view add conditional transform value by ids
	 *
	 * @Rest\View(serializerGroups={"report_view_add_conditional_transform_value.detail", "user.summary"})
	 *
	 * @Rest\Get("/reportviewaddconditionaltransformvalues" )
	 *
	 * @Rest\QueryParam(name="ids", nullable=true, description="an array of id")
	 *
	 * @ApiDoc(
	 *  section = "ReportViewAddConditionalTransformValue",
	 *  resource = true,
	 *  statusCodes = {
	 *      200 = "Returned when successful"
	 *  }
	 * )
	 *
	 * @param Request $request
	 * @return \UR\Model\Core\ReportViewAddConditionalTransformValueInterface[]
	 */
	public function getReportViewAddConditionalTransformValuesAction(Request $request)
	{
		$params = array_merge($request->query->all(), $request->attributes->all());
		$ids = $params['ids'];

		$reportViewAddConditionalTransformValueIds = json_decode($ids, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new BadRequestHttpException(sprintf('Expected ids is array of id, got', $ids));
		}

		if (!is_array($reportViewAddConditionalTransformValueIds) || empty($reportViewAddConditionalTransformValueIds)) {
			return [];
		}

        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $reportViewAddConditionalTransformValueRepository = $this->get('ur.repository.report_view_add_conditional_transform_value');
        $qb = $reportViewAddConditionalTransformValueRepository->getReportViewAddConditionalTransformValueQuery($user, $reportViewAddConditionalTransformValueIds, $this->getParams());

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
	}

	/**
	 * Update an existing report view add conditional transformer value from the submitted data or create new
	 *
	 * @ApiDoc(
	 *  section = "ReportViewAddConditionalTransformerValue",
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
	 * Update an existing report view add conditional transformer value from the submitted data or create a new at a specific location
	 *
	 * @ApiDoc(
	 *  section = "ReportViewAddConditionalTransformerValue",
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
	 * Delete an existing report view add conditional transform value
	 *
	 * @ApiDoc(
	 *  section = "ReportViewAddConditionalTransformerValue",
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
		return 'report_view_add_conditional_transformer_value';
	}

	/**
	 * The 'get' route name to redirect to after resource creation
	 *
	 * @return string
	 */
	protected function getGETRouteName()
	{
		return 'api_1_get_reportviewaddconditionaltransformvalue';
	}

	/**
	 * @return HandlerInterface
	 */
	protected function getHandler()
	{
		return $this->container->get('ur_api.handler.report_view_add_conditional_transform_value');
	}
}