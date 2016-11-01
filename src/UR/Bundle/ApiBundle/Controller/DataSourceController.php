<?php

namespace UR\Bundle\ApiBundle\Controller;

use DataDog\PagerBundle\Pagination;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Entity\Core\DataSourceEntry;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\User\Role\AdminInterface;

/**
 * @Rest\RouteResource("DataSource")
 */
class DataSourceController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data sources
     *
     * @Rest\View(serializerGroups={"datasource.summary", "dataSourceIntegration.summary", "user.summary"})
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
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceInterface[]
     */
    public function cgetAction(Request $request)
    {
        $publisher = $this->getUser();

        $dataSourceRepository = $this->get('ur.repository.data_source');
        $qb = $dataSourceRepository->getDataSourcesForUserQuery($publisher, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single data source for the given id
     *
     * @Rest\Get("/datasources/{id}", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"datasource.summary", "dataSourceIntegration.summary", "integration.summary","user.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Model\Core\DataSourceInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get a single data source for the given id
     *
     * @Rest\Get("/datasources/{id}/datasourceentries", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"datasource.summary", "dataSourceEntry.summary"})
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
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return DataSourceEntryInterface
     */
    public function getDataSourceEntriesAction(Request $request, $id)
    {
        $dataSource = $this->one($id);
        $dataSourceEntryRepository = $this->get('ur.repository.data_source_entry');
        $qb = $dataSourceEntryRepository->getDataSourceEntriesByDataSourceIdQuery($dataSource, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Generate API token for DataSource
     *
     * @Rest\Get("/datasources/{id}/apikey" )
     *
     * @Rest\View(serializerGroups={"datasource.apikey"})
     *
     * @ApiDoc(
     *  section = "Data Source",
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
    public function getApiKeyAction($id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);
        $apiKey = $dataSource->getApiKey();

        return $apiKey;
    }

    /**
     * Generate API token for DataSource
     *
     * @Rest\Get("/datasources/{id}/uremail" )
     *
     * @Rest\View(serializerGroups={"datasource.apikey"})
     *
     * @ApiDoc(
     *  section = "Data Source",
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
    public function getUrEmailAction($id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);
        $apiKey = $dataSource->getUrEmail();

        return $apiKey;
    }

    /**
     * Get data sources by API Key
     *
     * @Rest\Get("/datasources/byapikey")
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.summary", "integration.summary", "user.summary"})
     *
     * @Rest\QueryParam(name="apiKey", nullable=true, description="The API Key")
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
     * @return DataSourceInterface
     */
    public function getDataSourceByApiKeyAction(Request $request)
    {
        $apiKey = $request->query->get('apiKey', null);
        if (null === $apiKey) {
            throw new BadRequestHttpException('missing API Key');
        }

        $em = $this->get('ur.domain_manager.data_source');

        return $em->getDataSourceByApiKey($apiKey);
    }

    /**
     * Get data sources by publisher id
     *
     * @Rest\Get("/datasources/publisher/{id}")
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.summary", "integration.summary", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the publisher id
     * @return mixed
     * @throws NotFoundHttpException when resource not exist
     */
    public function getDataSourceByPublisherAction($id)
    {
        if (!$this->getUser() instanceof AdminInterface) {
            throw new InvalidArgumentException('only Admin has permission to view this resource');
        }

        $publisherManager = $this->get('ur_user.domain_manager.publisher');
        $publisher = $publisherManager->findPublisher($id);

        $em = $this->get('ur.domain_manager.data_source');

        return $em->getDataSourceForPublisher($publisher);
    }

    /**
     * Get data sources by API Key
     *
     * @Rest\Get("/datasources/byemailkey")
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.summary", "integration.summary","user.summary"})
     *
     * @Rest\QueryParam(name="apiKey", nullable=true, description="The API Key")
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
     * @return DataSourceInterface
     */
    public function getDataSourceByUrEmailAction(Request $request)
    {
        $emailKey = $request->query->get('emailkey', null);
        if (null === $emailKey) {
            throw new BadRequestHttpException('missing UR Email Key');
        }

        $em = $this->get('ur.domain_manager.data_source');

        return $em->getDataSourceByEmailKey($emailKey);
    }

    /**
     * Create a data source from the submitted data
     *
     * @ApiDoc(
     *  section = "Data Sources",
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
     * Upload
     *
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     *
     * @param $id
     * @return mixed
     */
    public function postUploadAction(Request $request, $id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);

        $uploadRootDir = $this->container->getParameter('upload_file_dir');
        $dirItem = '/' . $dataSource->getPublisherId() . '/' . $dataSource->getId() . '/' . (date_create('today')->format('Ymd'));
        $uploadPath = $uploadRootDir . $dirItem;

        /** @var FileBag $files */
        $files = $request->files;
        $em = $this->get('ur.domain_manager.data_source_entry');
        $result = $em->uploadDataSourceEntryFiles($files, $uploadPath, $dirItem, $dataSource);

        return $result;
    }

    /**
     * Update an existing data source from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Data Sources",
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
     * Update an existing data source from the submitted data or create a new data source at a specific location
     *
     * @ApiDoc(
     *  section = "Data Sources",
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
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);

        if (array_key_exists('publisher', $request->request->all())) {
            $publisher = (int)$request->get('publisher');
            if ($dataSource->getPublisherId() != $publisher) {
                throw new InvalidArgumentException('publisher in invalid');
            }
        }

        return $this->patch($request, $id);
    }

    /**
     * Delete an existing data source
     *
     * @ApiDoc(
     *  section = "Data Sources",
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
        return 'datasource';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_datasource';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_source');
    }
}