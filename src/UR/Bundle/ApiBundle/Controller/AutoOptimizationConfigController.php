<?php


namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;
use UR\Handler\HandlerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use UR\Service\AutoOptimization\ScoringServiceInterface;

/**
 * @Rest\RouteResource("autoOptimizationConfig")
 */
class AutoOptimizationConfigController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

    /**
     * Get all auto optimization config
     *
     * @Rest\View(serializerGroups={"auto_optimization_config.detail", "user.summary", "auto_optimization_config_data_set.summary", "dataset.summary"})
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
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return AutoOptimizationConfigInterface[]
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $AutoOptimizationConfigRepository = $this->get('ur.repository.auto_optimization_config');
        $qb = $AutoOptimizationConfigRepository->getAutoOptimizationConfigsForUserQuery($user, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single auto optimization config for the given id
     *
     * @Rest\View(serializerGroups={"auto_optimization_config.detail", "user.summary", "auto_optimization_config_data_set.summary", "dataset.summary"})
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return AutoOptimizationConfigInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get score (predict result)
     *
     * @Rest\View(serializerGroups={"auto_optimization_config.detail", "user.summary", "auto_optimization_config_data_set.summary", "dataset.summary"})
     *
     * @Rest\QueryParam(name="identifiers", nullable=true, description="identifier of ad tag")
     * @Rest\QueryParam(name="conditions", nullable=true, description="conditions of request")
     * @Rest\QueryParam(name="multiple", nullable=true, description="single predict or multiple predict")
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return AutoOptimizationConfigInterface
     */
    public function getScoreAction($id, Request $request)
    {
        $autoOptimizationConfig =  $this->one($id);
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return [];
        }

        /** @var ScoringServiceInterface $scoringService */
        $scoringService = $this->get('ur.service.auto_optimization.scoring_service');
        $params = array_merge($request->query->all(), $request->attributes->all());

        $identifiers = array_key_exists('identifiers', $params) ? $params['identifiers'] : "";
        $conditions = array_key_exists('conditions', $params) ? $params['conditions'] : "";
        $multiple = array_key_exists('multiple', $params) ? $params['multiple'] : false;

        if ($multiple) {
            return $scoringService->makeMultiplePredictions($autoOptimizationConfig, $identifiers, $conditions);
        }

        return $scoringService->makeOnePrediction($autoOptimizationConfig, $identifiers, $conditions);
    }

    /**
     * Get identifiers belong to an Auto Optimization Config
     *
     * @Rest\View(serializerGroups={"auto_optimization_config.detail", "user.summary", "auto_optimization_config_data_set.summary", "dataset.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return AutoOptimizationConfigInterface
     */
    public function getIdentifiersAction($id, Request $request)
    {
        $autoOptimizationConfig =  $this->one($id);
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return [];
        }

        $dataTrainingTableService = $this->get('ur.service.auto_optimization.data_training_table_service');
        $params = array_merge($request->query->all(), $request->attributes->all());

        return $dataTrainingTableService->getIdentifiersForAutoOptimizationConfig($autoOptimizationConfig, $params);
    }

    /**
     * Get training data belong to an Auto Optimization Config
     *
     * @Rest\QueryParam(name="identifiers", nullable=true, description="the identifiers of ad tags")
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return array
     */
    public function getDataAction($id, Request $request)
    {
        $autoOptimizationConfig = $this->one($id);
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return [];
        }
        $params = array_merge($request->query->all(), $request->attributes->all());

        $identifiers = [];
        if (array_key_exists('identifiers', $params)) {
            $identifiers = $params['identifiers'];
            $identifiers = explode(',', $identifiers);
        }

        $dataTrainingTableService = $this->get('ur.service.auto_optimization.data_training_table_service');

        return $dataTrainingTableService->getDataByIdentifiers($autoOptimizationConfig, $identifiers);
    }

    /**
     * Create a auto optimization config from the submitted data
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
     * Update an existing auto optimization config from the submitted data or create a new one
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
     * Update an existing auto optimization config from the submitted data or create a new one at a specific location
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
     * Delete an existing auto optimization config
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
        return 'autoOptimizationConfig';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_autooptimizationconfig';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.auto_optimization_config');
    }
}