<?php

namespace UR\Bundle\ApiBundle\Controller;

use DateTime;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;
use UR\Handler\HandlerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DateUtilInterface;
use UR\Service\OptimizationRule\OptimizationLearningFacadeServiceInterface;
use UR\Service\PublicSimpleException;

/**
 * @Rest\RouteResource("OptimizationRule")
 */
class OptimizationRuleController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

    /**
     * Get all optimizationRules
     *
     * @Rest\View(serializerGroups={"optimization_rule.detail", "user.summary", "report_view.detail", "report_view_data_set.summary", "dataset.summary"})
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
     *  section = "Optimization Rule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\OptimizationRuleInterface[]
     * @throws \Exception
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $optimizationRuleRepository = $this->get('ur.repository.optimization_rule');
        $qb = $optimizationRuleRepository->getOptimizationRulesForUserQuery($user, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get identifiers belong to an Optimization Rule
     *
     * @Rest\View(serializerGroups={"optimization_rule.detail", "user.summary", "report_view_data_set.summary", "dataset.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @ApiDoc(
     *  section = "OptimizationRule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return OptimizationRuleInterface
     */
    public function getIdentifiersAction($id, Request $request)
    {
        $optimizationRule = $this->one($id);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        $dataTrainingTableService = $this->get('ur.service.optimization_rule.data_training_table_service');

        return $dataTrainingTableService->getIdentifiersForOptimizationRule($optimizationRule);
    }

    /**
     * Get segments belong to an Optimization Rule
     *
     * @Rest\View(serializerGroups={"optimization_rule.detail", "user.summary", "dataset.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="identifier", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @ApiDoc(
     *  section = "OptimizationRule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return OptimizationRuleInterface
     */
    public function getSegmentsAction($id, Request $request)
    {
        $optimizationRule = $this->one($id);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        $dataTrainingTableService = $this->get('ur.service.optimization_rule.data_training_table_service');
        $params = array_merge($request->query->all(), $request->attributes->all());

        return $dataTrainingTableService->getSegmentFieldValuesByDateRange($optimizationRule, $params);
    }

    /**
     * Get identifiers by segments belong to an Optimization Rule
     *
     * @Rest\Get("/optimizationrules/{id}/segments/identifiers", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"optimization_rule.detail", "user.summary", "dataset.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="field", nullable=true, description="Segment field name")
     * @Rest\QueryParam(name="values", nullable=true, description="Segment values")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @ApiDoc(
     *  section = "OptimizationRule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return OptimizationRuleInterface
     */
    public function getIdentifierBySegmentsAction($id, Request $request)
    {
        $optimizationRule = $this->one($id);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        $dataTrainingTableService = $this->get('ur.service.optimization_rule.data_training_table_service');
        $params = array_merge($request->query->all(), $request->attributes->all());
        $segmentFieldValues = $params['segmentFieldValues'];

        return $dataTrainingTableService->getIdentifiersBySegmentsFieldValues($optimizationRule, $segmentFieldValues);
    }

    /**
     * Get training data belong to an Optimization Rule
     * @Rest\Post("/optimizationrules/{id}/data", requirements={"id" = "\d+"})
     *
     * @Rest\QueryParam(name="identifiers", nullable=true, description="the identifiers of ad tags")
     * @ApiDoc(
     *  section = "OptimizationRule",
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
     * @throws PublicSimpleException
     */
    public function postDataAction($id, Request $request)
    {
        $optimizationRuleScoreService = $this->get('ur.service.optimization_rule.optimization_rule_score_service');
        $optimizationRule = $this->one($id);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        if (!$optimizationRule->isFinishLoading()) {
            throw new PublicSimpleException('The scores are being calculated. Please wait a few minutes for the optimization to finish.');
        }

        $params = array_merge($request->query->all(), $request->request->all());

        $startDate = new DateTime('today');
        if (array_key_exists('startDate', $params)) {
            $startDate = date_create_from_format(DateUtilInterface::DATE_FORMAT, $params['startDate']);
        }

        $endDate = new DateTime('tomorrow');
        if (array_key_exists('endDate', $params)) {
            $endDate = date_create_from_format(DateUtilInterface::DATE_FORMAT, $params['endDate']);
        }

        $segmentFieldValues = [];
        if (array_key_exists('segmentFieldValues', $params)) {
            $segmentFieldValues = $params['segmentFieldValues'];
        }

        $result = $optimizationRuleScoreService->getFinalScores($optimizationRule, $segmentFieldValues, $startDate, $endDate);

        if (!array_key_exists('rows', $result) || empty($result['rows'])) {
            throw new PublicSimpleException('There is no data, please check the date range in optimization rule.');
        }

        return $result;
    }


    /**
     * Recalculate new scores for one Optimization Rule
     * @Rest\Post("/optimizationrules/{id}/rescore", requirements={"id" = "\d+"})
     *
     * @Rest\QueryParam(name="identifiers", nullable=true, description="the identifiers of ad tags")
     * @ApiDoc(
     *  section = "OptimizationRule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @param Request $request
     * @return array|int
     * @throws PublicSimpleException
     * @throws \Exception
     */
    public function postRescoreAction($id, Request $request)
    {
        $optimizationRuleRescoreService = $this->get('ur.service.optimization_rule.optimization_learning_facade_service');
        $optimizationRule = $this->one($id);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        /** @var OptimizationLearningFacadeServiceInterface $optimizationRuleRescoreService */
        $result = $optimizationRuleRescoreService->calculateNewScores($optimizationRule);

        if ($result ==  OptimizationLearningFacadeServiceInterface::UNCOMPLETED) {
            return $result;
        }

        $optimizerService = $this->get('ur.service.optimization_rule.automated_optimization.automated_optimizer');
        $optimizationRule = $this->one($id); //Note: Do not remove this line
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        return $optimizerService->optimizeForRule($optimizationRule);
    }


    /**
     * Get training data belong to an Optimization Rule
     *
     * @Rest\QueryParam(name="identifiers", nullable=true, description="the identifiers of ad tags")
     * @ApiDoc(
     *  section = "OptimizationRule",
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
    public function getRuleDataAction($id, Request $request)
    {
        $optimizationRule = $this->one($id);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }
        $params = array_merge($request->query->all(), $request->attributes->all());

        $identifiers = [];
        if (array_key_exists('identifiers', $params)) {
            $identifiers = $params['identifiers'];
            $identifiers = explode(',', $identifiers);
        }

        $dataTrainingTableService = $this->get('ur.service.optimization_rule.data_training_table_service');

        return $dataTrainingTableService->getDataByIdentifiers($optimizationRule, $identifiers);
    }

    /**
     * Get a optimization rule by id
     *
     * @Rest\View(serializerGroups={"optimization_rule.detail", "user.summary", "report_view.detail", "report_view_data_set.summary", "dataset.summary"})
     *
     * @ApiDoc(
     *  section = "Optimization",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return OptimizationRuleInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a optimization rule from the submitted data
     *
     * @ApiDoc(
     *  section = "Optimizatin Rule",
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
     * Update an existing optimization rule from the submitted data or create a new optimization rule
     *
     * @ApiDoc(
     *  section = "Optimization Rule",
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
     *  section = "Optimization Rule",
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
     * Delete an existing Optimization Rule
     *
     * @ApiDoc(
     *  section = "Optimization Rule",
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
        return 'optimizationrule';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_optimizationrule';
    }

    /**
     * @return object|HandlerInterface
     */

    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.optimization_rule');
    }
}