<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSourceIntegrationScheduleManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\FetcherSchedule;
use UR\Model\Core\IntegrationInterface;
use UR\Repository\Core\DataSourceIntegrationScheduleRepositoryInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

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
     * @Rest\View(serializerGroups={"fetcherschedule.detail", "dataSourceIntegrationBackfillHistory.summary", "dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.bySchedule", "integration.detail", "user.summary"})
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
        /** @var DataSourceIntegrationScheduleRepositoryInterface $schedulesRepository */
        $schedulesRepository = $this->get('ur.repository.data_source_integration_schedule');
        $qb = $schedulesRepository->findToBeExecuted();
        $result = $this->getPagination($qb, $request);

        $notExecutedNormalSchedules = $result['records'];
        $normalSchedules = [];
        foreach ($notExecutedNormalSchedules as $notExecutedNormalSchedule) {
            if (!$notExecutedNormalSchedule instanceof DataSourceIntegrationScheduleInterface) {
                continue;
            }

            $fetcherSchedule = (new FetcherSchedule())
                ->setDataSourceIntegrationSchedule($notExecutedNormalSchedule);

            $normalSchedules[] = $fetcherSchedule;
        }
        $result['records'] = $normalSchedules;

        return $result;
    }

    /**
     * Get integration to be executed due to schedule
     *
     * @Rest\Get("/datasourceintegrationschedules/bydatasource")
     *
     * @Rest\View(serializerGroups={"fetcherschedule.detail", "dataSourceIntegrationBackfillHistory.summary", "dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.bySchedule", "integration.detail", "user.summary"})
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

        $normalSchedules = $this->findByDataSource($request);

        $normalSchedules = array_map(function ($schedule) {
            if ($schedule instanceof DataSourceIntegrationScheduleInterface) {
                $fetcherSchedule = new FetcherSchedule();
                $fetcherSchedule->setDataSourceIntegrationSchedule($schedule);
                return $fetcherSchedule;
            }
        }, $normalSchedules);


        return $normalSchedules;
    }

    /**
     * Get integration to be executed due to schedule
     *
     * @Rest\Get("/datasourceintegrationschedules/byintegration")
     *
     * @Rest\View(serializerGroups={"fetcherschedule.detail", "dataSourceIntegrationBackfillHistory.summary", "dataSourceIntegrationSchedule.detail", "datasource.detail", "dataSourceIntegration.bySchedule", "integration.detail", "user.summary"})
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
    public function getDataSourceIntegrationSchedulesByIntegrationAction(Request $request)
    {
        // The REST naming is not standard. This REST Api seems to be that use the cgetAction()
        // But because of the difference from serializer groups, we must use this REST API:
        $normalSchedules = $this->findByIntegration($request);

        $normalSchedules = array_map(function ($schedule) {
            if ($schedule instanceof DataSourceIntegrationScheduleInterface) {
                $fetcherSchedule = new FetcherSchedule();
                $fetcherSchedule->setDataSourceIntegrationSchedule($schedule);
                return $fetcherSchedule;
            }
        }, $normalSchedules);

        return $normalSchedules;
    }

    /**
     * update executeAt time
     *
     * @Rest\Post("/datasourceintegrationschedules/finishorfail")
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
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationInterface[]
     */
    public function postUpdateFinishOrFailAction(Request $request)
    {
        $uuid = $request->request->get('uuid', null);
        if (null === $uuid) {
            throw new InvalidArgumentException('Expected uuid of data source integration schedule');
        }

        /** @var DataSourceIntegrationScheduleRepositoryInterface $dataSourceIntegrationScheduleRepository */
        $dataSourceIntegrationScheduleRepository = $this->get('ur.repository.data_source_integration_schedule');
        /** @var DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule */
        $dataSourceIntegrationSchedule = $dataSourceIntegrationScheduleRepository->findByUUID($uuid);
        if (!$dataSourceIntegrationSchedule instanceof DataSourceIntegrationScheduleInterface) {
            throw new InvalidArgumentException(sprintf('Schedule with UUID (%s) not found, may be deleted by hand or changed from UI while fetcher is running.', $uuid));
        }

        // check permission
        $this->checkUserPermission($dataSourceIntegrationSchedule, 'edit');

        /*
         * important: only update executedAt if pending. This is for avoiding race condition between fetcher and ur api
         * When fetcher running, the pending is set to true.
         * But the ui may change the schedule setting (and set pending to false).
         * => When fetcher finishes and update the next executedAt, the setting from UI is overwrite.
         */
        if ($dataSourceIntegrationSchedule->getStatus() != DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_PENDING) {
            return $this->view(true, Codes::HTTP_OK);
        }

        $dataSourceIntegrationScheduleManager = $this->get('ur.domain_manager.data_source_integration_schedule');

        // do update
        //// required param
        $statusParam = $request->request->get(DataSourceIntegrationBackfillHistoryInterface::FIELD_STATUS, null);
        if (null === $statusParam
            || !in_array($statusParam, [
                DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_FINISHED,
                DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_FAILED
            ])
        ) {
            throw new InvalidArgumentException('missing status or status is invalid');
        }

        /** Firstly save status, as FINISH or FAILED */
        $dataSourceIntegrationSchedule->setStatus($statusParam);
        $dataSourceIntegrationScheduleManager->save($dataSourceIntegrationSchedule);

        /** Save nextExecutedAt */
        // important: update nextExecutedAt and status for next execution
        // very important!!! Must save immediately nextExecutedAt before updating the status to not-run
        // this will avoid race condition between ur api and fetcher
        // i.e when status is set to not-run to database but the nextExecutedAt is not enough quickly,
        // the fetcher activator may call again and get this data source integration schedule again
        $nextExecutedUtil = $this->get('ur.service.date_time.next_executed_at');
        $dataSourceIntegrationSchedule = $nextExecutedUtil->updateDataSourceIntegrationSchedule($dataSourceIntegrationSchedule);
        $dataSourceIntegrationSchedule->setFinishedAt(date_create('now')->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE)));
        $dataSourceIntegrationScheduleManager->save($dataSourceIntegrationSchedule);

        /** Reset status to NOT-RUN */
        $dataSourceIntegrationSchedule->setStatus(DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN);
        $dataSourceIntegrationScheduleManager->save($dataSourceIntegrationSchedule);


        return $this->view(true, Codes::HTTP_OK);
    }

    /**
     * update executeAt time
     *
     * @Rest\Post("/datasourceintegrationschedules/pending")
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
     * @return View
     */
    public function postUpdatePendingAction(Request $request)
    {
        $uuid = $request->request->get('uuid', null);
        if (null === $uuid) {
            throw new InvalidArgumentException('Expected uuid of data source integration schedule');
        }

        /** @var DataSourceIntegrationScheduleRepositoryInterface $dataSourceIntegrationScheduleRepository */
        $dataSourceIntegrationScheduleRepository = $this->get('ur.repository.data_source_integration_schedule');
        /** @var DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule */
        $dataSourceIntegrationSchedule = $dataSourceIntegrationScheduleRepository->findByUUID($uuid);
        if (!$dataSourceIntegrationSchedule instanceof DataSourceIntegrationScheduleInterface) {
            throw new InvalidArgumentException(sprintf('Schedule with UUID (%s) not found, may be deleted by hand or changed from UI while fetcher is running.', $uuid));
        }

        // check permission
        $this->checkUserPermission($dataSourceIntegrationSchedule, 'edit');
        $dataSourceIntegrationScheduleManager = $this->get('ur.domain_manager.data_source_integration_schedule');
        /* case: status = 1 (has an unexpected exception will occur, status = 1, nextExecuted < currentDate, queueAt < yesterday ) */
        if ($dataSourceIntegrationSchedule->getStatus() == DataSourceIntegrationScheduleInterface::FETCHER_STATUS_PENDING) {
            $queuedAt = new \DateTime();
            $queuedAt->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));
            $dataSourceIntegrationSchedule->setQueuedAt($queuedAt);

            // reset finishedAt
            $dataSourceIntegrationSchedule->setFinishedAt(null);
            $dataSourceIntegrationScheduleManager->save($dataSourceIntegrationSchedule);

            return $this->view(true, Codes::HTTP_OK);
        }

        /*
         * important: only update executedAt if pending. This is for avoiding race condition between fetcher and ur api
         * When fetcher running, the pending is set to true.
         * But the ui may change the schedule setting (and set pending to false).
         * => When fetcher finishes and update the next executedAt, the setting from UI is overwrite.
         */
        if ($dataSourceIntegrationSchedule->getStatus() != DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN) {
            throw new InvalidArgumentException(sprintf('This data source integration is not in not-run status (real status: %d', $dataSourceIntegrationSchedule->getStatus()));
        }

        // do update
        $dataSourceIntegrationSchedule->setStatus(DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_PENDING);

        //// option param
        $queuedAtParam = $request->request->get(DataSourceIntegrationBackfillHistoryInterface::FIELD_QUEUED_AT, null);

        try {
            $queuedAt = null !== $queuedAtParam ? date_create($queuedAtParam) : new \DateTime();
        } catch (\Exception $e) {
            $queuedAt = new \DateTime(); // not throw exception to continue updating schedule
        }

        $queuedAt->setTimezone(new \DateTimeZone(DateFormat::DEFAULT_TIMEZONE));
        $dataSourceIntegrationSchedule->setQueuedAt($queuedAt);

        // reset finishedAt
        $dataSourceIntegrationSchedule->setFinishedAt(null);

        $dataSourceIntegrationScheduleManager->save($dataSourceIntegrationSchedule);

        return $this->view(true, Codes::HTTP_OK);
    }

    /**
     * Update an existing data source integration schedule from the submitted data or create a new data source integration schedule at a specific location
     *
     * @ApiDoc(
     *  section = "Data Sources Integration Schedule",
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
     * find By DataSource
     *
     * @param Request $request
     * @return array|\UR\Model\Core\DataSourceIntegrationScheduleInterface[]
     */
    private function findByDataSource(Request $request)
    {
        $dataSourceId = $request->query->get('dataSource', null);
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
     * find By Integration Id
     *
     * @param Request $request
     * @return array|\UR\Model\Core\DataSourceIntegrationScheduleInterface[]
     */
    private function findByIntegration(Request $request)
    {
        $integrationId = $request->query->get('integration', null);
        $integrationId = filter_var($integrationId, FILTER_VALIDATE_INT);
        if (false === $integrationId || $integrationId < 0) {
            throw new BadRequestHttpException(sprintf('Invalid integration id %s', $integrationId));
        }

        /** @var DataSourceManagerInterface $dataSourceManager */
        $integrationManager = $this->get('ur.domain_manager.integration');
        $integration = $integrationManager->find($integrationId);
        if (!$integration instanceof IntegrationInterface) {
            throw new NotFoundHttpException(
                sprintf("The %s resource '%s' was not found or you do not have access", $this->getResourceName(), $integrationId)
            );
        }

        // check permission
        $this->checkUserPermission($integration);

        // find and return
        /** @var DataSourceIntegrationScheduleManagerInterface $dsisManager */
        $dsisManager = $this->get('ur.domain_manager.data_source_integration_schedule');

        return $dsisManager->findByIntegration($integration);
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