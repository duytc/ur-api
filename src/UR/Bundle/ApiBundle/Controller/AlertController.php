<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;
use UR\Handler\HandlerInterface;
use UR\Model\AlertPagerParam;
use UR\Model\Core\AlertInterface;
use UR\Model\PagerParam;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;

/**
 * @Rest\RouteResource("Alert")
 */
class AlertController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

    /**
     * Get all alerts
     *
     * @Rest\View(serializerGroups={"alert.detail", "user.summary", "datasource.viewAlert"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @Rest\QueryParam(name="types", nullable=true, description="the type to get")
     *
     * @ApiDoc(
     *  section = "Alert",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\AlertInterface[]
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $alertRepository = $this->get('ur.repository.alert');
        $qb = $alertRepository->getAlertsForUserQuery($user, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single alert group for the given id
     *
     * @Rest\View(serializerGroups={"alert.detail", "user.summary", "datasource.viewAlert"})
     *
     * @ApiDoc(
     *  section = "Alert",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return AlertInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a alert from the submitted data
     *
     * @ApiDoc(
     *  section = "Alert",
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
     * Update an existing alert from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Alert",
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
     * Update an existing alert from the submitted data or create a new alert at a specific location
     *
     * @ApiDoc(
     *  section = "Alert",
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
     * Update an array existing alert
     *
     * @Rest\Put("/alerts")
     * @Rest\QueryParam(name="delete", requirements="(true|false)", nullable=true, description="delete alerts")
     * @Rest\QueryParam(name="status", requirements="(true|false)", nullable=true, description="status alerts")
     *
     * @ApiDoc(
     *  section = "Alert",
     *  resource = true,
     *  statusCodes = {
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     * @return mixed
     * @throws \Exception
     */
    public function putAlertsAction(Request $request)
    {
        $alertManager = $this->get('ur.domain_manager.alert');
        $params = $request->request->all();

        $ids = $params['ids'];
        $delete = filter_var($request->query->get('delete', null), FILTER_VALIDATE_BOOLEAN);
        $status = filter_var($request->query->get('status', null), FILTER_VALIDATE_BOOLEAN);

        if ($delete === true) {
            return $alertManager->deleteAlertsByIds($ids);
        } else if ($status === true) {
            return $alertManager->updateMarkAsReadByIds($ids);
        } else if ($status === false) {
            return $alertManager->updateMarkAsUnreadByIds($ids);
        }

        throw new \Exception("param is not valid");
    }

    /**
     * Delete an existing alert
     *
     * @ApiDoc(
     *  section = "Alert",
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
        return 'alert';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_alert';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.alert');
    }

    /**
     * override parent function
     * @inheritdoc
     */
    protected function _createParams(array $params)
    {
        // create a params array with all values set to null
        $defaultParams = array_fill_keys([
            AlertPagerParam::PARAM_SEARCH_FIELD,
            AlertPagerParam::PARAM_SEARCH_KEY,
            AlertPagerParam::PARAM_SORT_FIELD,
            AlertPagerParam::PARAM_SORT_DIRECTION,
            AlertPagerParam::PARAM_PUBLISHER_ID,
            AlertPagerParam::PARAM_FILTER_TYPES
        ], null);

        $params = array_merge($defaultParams, $params);
        $publisherId = intval($params[AlertPagerParam::PARAM_PUBLISHER_ID]);

        return new AlertPagerParam($params[PagerParam::PARAM_SEARCH_FIELD], $params[AlertPagerParam::PARAM_SEARCH_KEY], $params[AlertPagerParam::PARAM_SORT_FIELD], $params[AlertPagerParam::PARAM_SORT_DIRECTION], $publisherId, $params[AlertPagerParam::PARAM_FILTER_TYPES]);
    }
}