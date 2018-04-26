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
use UR\Model\Core\OptimizationIntegrationInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use UR\Service\OptimizationRule\OptimizationRuleScoreServiceInterface;

/**
 * @Rest\RouteResource("OptimizationIntegration")
 */
class OptimizationIntegrationController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

    /**
     * Get all optimizationIntegrations
     *
     * @Rest\View(serializerGroups={"optimizationIntegration.detail", "optimization_rule.detail", "user.summary", "report_view.detail", "report_view_data_set.summary", "dataset.summary"})
     *
     * @Rest\QueryParam(name="optimizationRule", nullable=true, requirements="\d+", description="the optimization rule id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "Optimization Rule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\OptimizationIntegrationInterface[]
     * @throws \Exception
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');
        $optimizationRuleId = $request->query->get('optimizationRule', null);
        $optimizationIntegrationRepository = $this->get('ur.repository.optimization_integration');

        if (isset($optimizationRuleId) && !empty($optimizationRuleId)) {
            $qb = $optimizationIntegrationRepository->getOptimizationIntegrationsForOptimizationRuleQuery($user, $optimizationRuleId, $this->getParams());
        } else {
            $qb = $optimizationIntegrationRepository->getOptimizationIntegrationsQuery($user, $this->getParams());
        }

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a optimization integration by id
     *
     * @Rest\View(serializerGroups={"optimizationIntegration.detail", "optimization_rule.detail", "user.summary", "report_view.detail", "report_view_data_set.summary", "dataset.summary"})
     *
     * @ApiDoc(
     *  section = "Optimization Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return OptimizationIntegrationInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a optimization integration from the submitted data
     *
     * @ApiDoc(
     *  section = "Optimizatin Config",
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
     * Update an existing optimization integration from the submitted data or create a new optimization rule
     *
     * @ApiDoc(
     *  section = "Optimization integration",
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
     * Update an existing optimizatin from the submitted data
     *
     * @ApiDoc(
     *  section = "Optimization Integration",
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
     * Delete an existing Optimization Integration
     *
     * @ApiDoc(
     *  section = "Optimization Integration",
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
     * Get Ad Slot ids has been assigned optimization integration
     *
     * @Rest\Get("/optimizationintegrations/adslot/ids")
     *
     * @Rest\QueryParam(name="id", nullable=false, description="optimization integration id")
     * @ApiDoc(
     *  section = "Optimization Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getAdSlotIdsAction(Request $request)
    {
        $optimizationIntegrationId = $request->query->get('id', null);

        $optimizationIntegrationManager = $this->get('ur.domain_manager.optimization_integration');
        $adSlotIds = $optimizationIntegrationManager->getOptimizationIntegrationAdSlotIds($optimizationIntegrationId);

        $adSlotIdsAssigned = [];
        foreach ($adSlotIds as $adSlotId) {
            $adSlotIdsAssigned[$adSlotId] = $adSlotId;
        }

        return $adSlotIdsAssigned;
    }

    /**
     * Get segments form optimization integration based on adSlot Id
     *
     * @Rest\Get("/optimizationintegrations/adslot/{id}/segments")
     *
     * @ApiDoc(
     *  section = "Optimization Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return mixed
     * @throws \Exception
     */
    public function getSegmentsForAdSlotAction(Request $request, $id)
    {
        $adSlotId = (int) $id;
        $optimizationIntegrationRepository = $this->get('ur.repository.optimization_integration');

        // get all optimization
        // get segments based on $adSlotId
        $optimizationIntegration = $optimizationIntegrationRepository->getSegmentsByAdSlotId($adSlotId);

        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return [];
        }

        $segments = [];
        foreach ($optimizationIntegration->getSegments() as $segment) {
            if (array_key_exists('dimension', $segment)) {
                $segments [] = $segment['dimension'];
            }
        }

        return $segments;
    }

    /**
     * Get segments value form segment values from historical data
     *
     * @Rest\Get("/optimizationintegrations/adslot/{id}/segmentvalues")
     *
     * @Rest\QueryParam(name="segment", nullable=true, description="segment")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     *
     * @ApiDoc(
     *  section = "Optimization Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return mixed
     * @throws \Exception
     */
    public function getSegmentValuesForAdSlotAction(Request $request, $id)
    {
        $params = array_merge($request->query->all(), $request->attributes->all());
        $adSlotId = (int)$id;

        if (empty($params['segment'])) {
            throw new \Exception('Expect segment value.');
        }

        $optimizationIntegrationRepository = $this->get('ur.repository.optimization_integration');
        $dataTrainingTableService = $this->get('ur.service.optimization_rule.data_training_table_service');
        // get all optimization, get segments based on $adSlotId
        $optimizationIntegration = $optimizationIntegrationRepository->getSegmentsByAdSlotId($adSlotId);

        if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
            return [];
        }

        $data = $dataTrainingTableService->getSegmentValuesByAdSlotId($optimizationIntegration, $params);

        // delete global value
        foreach ($data as $key => $value) {
            if (OptimizationRuleScoreServiceInterface::GLOBAL_KEY == $value) {
                unset($data[$key]);
                break;
            }
        }

        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        $offset = ($page - 1) * $limit;

        return array(
            'totalRecord' => count($data),
            'records' => array_values(array_slice($data, $offset, $limit)),
            'itemPerPage' => $limit,
            'currentPage' => $page
        );
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'optimizationintegration';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_optimizationintegration';
    }

    /**
     * @return object|HandlerInterface
     */

    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.optimization_integration');
    }
}