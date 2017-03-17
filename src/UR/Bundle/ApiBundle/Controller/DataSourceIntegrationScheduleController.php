<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSourceIntegrationScheduleManagerInterface;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;

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
     * @ApiDoc(
     *  section = "Data Source Integration Schedule",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return DataSourceIntegrationScheduleInterface[]
     */
    public function cgetAction()
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
        $now = new \DateTime();

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