<?php

namespace UR\Bundle\ApiBundle\Controller;

use DataDog\PagerBundle\Pagination;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\User\Role\AdminInterface;

/**
 * @Rest\RouteResource("datasourceentry")
 */
class DataSourceEntryController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data source entries
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceEntry.summary", "user.summary"})
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
     *  section = "Data Source Entry",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceEntryInterface[]
     */
    public function cgetAction(Request $request)
    {
        $publisher = $this->getUser();

        $dataSourceEntryRepository = $this->get('ur.repository.data_source_entry');
        $qb = $dataSourceEntryRepository->getDataSourceEntriesForDataSourceQuery($publisher, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['limit'])) {
            $pagination = new Pagination($qb, $request);
            return array(
                'totalRecord' => $pagination->total(),
                'records' => $qb->getQuery()->getResult(),
                'itemPerPage' => $pagination->total(),
                'currentPage' => $pagination->currentPage()
            );
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single data source entry for the given id
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceEntry.summary", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source Entry",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Model\Core\DataSourceEntryInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get data sources by publisher id
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceEntry.summary", "user.summary"})
     *
     * @Rest\Get("/datasourceentries/publisher/{id}")
     *
     * @ApiDoc(
     *  section = "Data Source Entry",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the publisher id
     * @return array
     * @throws NotFoundHttpException when resource not exist
     */
    public function getDataSourceEntryByPublisherAction($id)
    {
        if (!$this->getUser() instanceof AdminInterface) {
            throw new InvalidArgumentException('only Admin has permission to create this resource');
        }

        $publisherManager = $this->get('ur_user.domain_manager.publisher');
        $publisher = $publisherManager->findPublisher($id);

        $em = $this->get('ur.domain_manager.data_source_entry');

        return $em->getDataSourceEntryForPublisher($publisher);
    }

    /**
     * Download a DataSource Entry
     *
     * @Rest\Get("/datasourceentries/{id}/download" )
     *
     * @ApiDoc(
     *  section = "Data Source Entry",
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
    public function downloadFileAction($id)
    {
        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->one($id);
        $filePath = $dataSourceEntry->getPath();
        $fs = new Filesystem();

        if (!$fs->exists($filePath)) {
            throw $this->createNotFoundException();
        }

        return array(
            'file_path' => $filePath,
            'file_name' => basename($filePath),
            'content' => file_get_contents($filePath),
            'format' => $dataSourceEntry->getDataSource()->getFormat()
        );
    }

    /**
     * Create a data source entry from the submitted data
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
     * Update an existing data source entry from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
     * Update an existing data source entry from the submitted data or create a new data source at a specific location
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
     * Delete an existing data source entry
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
        return 'datasourceentry';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_datasourceentry';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_source_entry');
    }
}