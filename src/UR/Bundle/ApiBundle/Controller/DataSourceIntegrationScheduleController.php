<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSourceIntegrationScheduleManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;

/**
 * @Rest\RouteResource("datasourceintegrationschedules")
 */
class DataSourceIntegrationScheduleController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data sources integration
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.detail", "integration.detail", "user.summary"})
     *
     * @Rest\QueryParam(name="datasource", nullable=true, requirements="\d+", description="the datasource id")
     *
     * @ApiDoc(
     *  section = "Data Source Integration Schedule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationScheduleInterface[]
     */
    public function cgetAction(Request $request)
    {
        return $this->all();
    }

    /**
     * Get a single Data Source Integration Schedule for the given id
     *
     * @Rest\Get("/datasourceintegrationschedules/{id}", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.detail", "integration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source Integration Schedule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return mixed
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get integration to be executed due to schedule
     *
     * @Rest\Get("/datasourceintegrationschedules/byschedule")
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.bySchedule", "integration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return DataSourceIntegrationScheduleInterface[]
     */
    public function getIntegrationByScheduleAction(Request $request)
    {
        /** @var DataSourceIntegrationScheduleManagerInterface $dsisManager */
        $dsisManager = $this->get('ur.domain_manager.data_source_integration_schedule');

        return $dsisManager->findToBeExecuted();
    }

    /**
     * Get integration to be executed due to schedule
     *
     * @Rest\Get("/datasourceintegrationschedules/bydatasource")
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.bySchedule", "integration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return DataSourceIntegrationScheduleInterface[]
     */
    public function getDataSourceIntegrationSchedulesByDataSourceAction(Request $request)
    {
        // The REST naming is not standard. This REST Api seems to be that use the cgetAction()
        // But because of the difference from serializer groups, we must use this REST API:
        // - the cgetAction: use group dataSourceIntegration.detail that return datasourceintegration params contain transformed-value 'null' if type is 'secure'
        // - this action: use group dataSourceIntegration.bySchedule that return original datasourceintegration params (so value is original value)
        // TODO: move to action cgetAction if can do it

        return $this->findByDataSource($request);
    }

    /**
     * update executeAt time
     *
     * @Rest\Post("/datasourceintegrationschedules/updateexecuteat")
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.detail", "integration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationInterface[]
     */
    public function postUpdateExecuteAtAction(Request $request)
    {
        $id = $request->request->get('id', null);
        $dataSourceIntegrationSchedule = $this->one($id);
        if (!($dataSourceIntegrationSchedule instanceof DataSourceIntegrationScheduleInterface)) {
            throw new NotFoundHttpException('Not found that Data Source Integration Schedule');
        }

        $executedAt = $dataSourceIntegrationSchedule->getExecutedAt();
        $scheduleType = $dataSourceIntegrationSchedule->getScheduleType();
        $scheduleSetting = $dataSourceIntegrationSchedule->getDataSourceIntegration()->getSchedule();
        $now = new \DateTime('now', new \DateTimeZone('UTC'));

        switch ($scheduleType) {
            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY:
                $scheduleHours = $scheduleSetting[DataSourceIntegration::SCHEDULE_KEY_CHECK_EVERY][DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR];
                $executedAt = clone $now;
                $executedAt->add(new \DateInterval(sprintf('PT%dH', $scheduleHours))); // increase n hours

                break;

            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_AT:
                $executedAt = clone $now;
                $executedAt->add(new \DateInterval(sprintf('P1D'))); // increase 1 day

                break;

            default:
                break;
        }

        /** @var DataSourceIntegrationScheduleManagerInterface $dsisManager */
        $dsisManager = $this->get('ur.domain_manager.data_source_integration_schedule');
        $dsisManager->updateExecuteAt($dataSourceIntegrationSchedule, $executedAt);

        return $this->view(true, Codes::HTTP_OK);
    }

    /**
     * find By DataSource
     *
     * @param Request $request
     * @return array|\UR\Model\Core\DataSourceIntegrationScheduleInterface[]
     */
    private function findByDataSource(Request $request)
    {
        $dataSourceId = $request->query->get('datasource', null);
        $dataSourceId = filter_var($dataSourceId, FILTER_VALIDATE_INT);
        if (false === $dataSourceId || $dataSourceId < 0) {
            throw new BadRequestHttpException(sprintf('Invalid datasource id %s', $dataSourceId));
        }

        /** @var DataSourceManagerInterface $dataSourceManager */
        $dataSourceManager = $this->get('ur.domain_manager.data_source');
        $dataSource = $dataSourceManager->find($dataSourceId);
        if (!$dataSource instanceof DataSourceInterface) {
            throw new NotFoundHttpException(
                sprintf("The %s resource '%s' was not found or you do not have access", $this->getResourceName(), $dataSourceId)
            );
        }

        // check permission
        $this->checkUserPermission($dataSource);

        // find and return
        /** @var DataSourceIntegrationScheduleManagerInterface $dsisManager */
        $dsisManager = $this->get('ur.domain_manager.data_source_integration_schedule');

        return $dsisManager->findByDataSource($dataSource);
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'data_source_integration_schedule';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_data_source_integration_schedule';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_source_integration_schedule');
    }
}