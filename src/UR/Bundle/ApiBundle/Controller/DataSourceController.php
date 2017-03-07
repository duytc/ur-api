<?php

namespace UR\Bundle\ApiBundle\Controller;

use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\DataSourceEntry;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\PublisherInterface;

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
     * @Rest\View(serializerGroups={"datasource.summary", "dataSourceIntegration.summary", "integration.summary", "user.summary"})
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
     * @param $id
     * @return mixed
     */
    public function getDetectedfieldsAction($id)
    {
        /**
         * @var DataSourceInterface $dataSource
         */
        $dataSource = $this->one($id);

        return $dataSource->getDetectedFields();
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
     * validate ur email
     *
     * @Rest\Get("/datasources/emailvalidations")
     *
     * @Rest\QueryParam(name="email", nullable=false, description="The UR email that need be validated")
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
     * @return bool
     */
    public function validateEmailAction(Request $request)
    {
        $email = $request->query->get('email', null);
        if (null === $email) {
            throw new BadRequestHttpException('missing "email"');
        }

        $dataSourceManager = $this->get('ur.domain_manager.data_source');
        $dataSource = $dataSourceManager->getDataSourceByEmailKey($email);

        return empty($dataSource) ? false : true;
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
     * Get data sources by API Key
     *
     * @Rest\Get("/datasources/byemail")
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.summary", "integration.summary","user.summary"})
     *
     * @Rest\QueryParam(name="email", nullable=false, description="The email")
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
        $emailKey = $request->query->get('email', null);
        if (null === $emailKey) {
            throw new BadRequestHttpException('missing "email" key');
        }

        $em = $this->get('ur.domain_manager.data_source');

        return $em->getDataSourceByEmailKey($emailKey);
    }


    /**
     * Get data sources for a integration and a publisher.
     * This is used for integrating all integration modules (integration, email) into this ur system
     *
     * @Rest\Get("/datasources/byintegration")
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.summary", "integration.summary","user.summary"})
     *
     * @Rest\QueryParam(name="integration", nullable=false, description="The integration cname")
     * @Rest\QueryParam(name="publisher", nullable=false, description="The publisher id")
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
    public function getDataSourcesByIntegrationAndPublisherAction(Request $request)
    {
        /* get params */
        $integrationCName = $request->query->get('integration', null);
        $publisherId = $request->query->get('publisher', null);
        if (null === $integrationCName || null === $publisherId) {
            throw new BadRequestHttpException('missing integration id or publisher id');
        }

        /* find and check permission for integration */
        $integrationManager = $this->get('ur.domain_manager.integration');
        $integration = $integrationManager->findByCanonicalName($integrationCName);
        if (!($integration instanceof IntegrationInterface)) {
            throw new NotFoundHttpException(
                sprintf("The %s resource '%s' was not found or you do not have access", 'integration', $integrationCName)
            );
        }
        $this->checkUserPermission($integration);

        /* find and check permission for publisher */
        $publisherManager = $this->get('ur_user.domain_manager.publisher');
        $publisher = $publisherManager->find($publisherId);
        if (!($publisher instanceof PublisherInterface)) {
            throw new NotFoundHttpException(
                sprintf("The %s resource '%s' was not found or you do not have access", 'publisher', $publisherId)
            );
        }
        $this->checkUserPermission($publisher);

        /* find and return result */
        $dataSourceRepository = $this->get('ur.repository.data_source');
        $dataSources = $dataSourceRepository->getDataSourcesByIntegrationAndPublisher($integration, $publisher);
        return $dataSources;
    }

    /**
     * Get all import history by datasource
     *
     * @Rest\Get("datasources/{id}/importhistories", requirements={"id" = "\d+"})
     * @Rest\View(serializerGroups={"importHistory.detail", "user.summary", "dataset.importhistory", "dataSourceEntry.summary", "datasource.importhistory"})
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
     * @param int $id the resource id
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getImportHistoriesByDataSourceAction(Request $request, $id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);
        $importHistoryManager = $this->get('ur.domain_manager.import_history');
        $qb = $importHistoryManager->getImportedHistoryByDataSourceQuery($dataSource, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
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
     * @Rest\Post("/datasources/{id}/uploadfordetectedfields")
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
    public function postUploadForDetectedFieldsAction(Request $request, $id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);
        /** @var FileBag $files */
        $files = $request->files;
        $dirItem = '/' . $dataSource->getPublisherId() . '/' . $dataSource->getId() . '/' . (date_create('today')->format('Ymd'));
        $importService = $this->get('ur.service.import');
        $result = $importService->detectedFieldsFromFiles($files, $dirItem, $dataSource);
        if (!is_array($result)) {
            throw new BadRequestHttpException('Could not detect fields from uploaded file');
        }

        return new JsonResponse($result);
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

        /** @var FileBag $fileBag */
        $fileBag = $request->files;

        return $this->processUploadedFiles($dataSource, $fileBag, $via = DataSourceEntry::RECEIVED_VIA_UPLOAD);
    }

    /**
     * Regenerate Email
     * @Rest\Post("/datasources/{id}/regenerateuremails")
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param $id
     * @return mixed
     */
    public function postRegenerateUrEmailAction($id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);

        $regenEmailService = $this->container->get('ur.service.datasource.regenerate_email');
        $regenEmailService->regenerateUrEmail($dataSource->getId());
    }

    /**
     * Regenerate Api Key
     * @Rest\Post("/datasources/{id}/regenerateurapikeys")
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param $id
     * @return mixed
     */
    public function postRegenerateUrApiKeyAction($id)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);

        $regenEmailService = $this->container->get('ur.service.datasource.regenerate_api_key');
        $regenEmailService->regenerateUrApiKey($dataSource->getId());
    }

    /**
     * Upload file to multiple data sources
     *
     * @Rest\Post("/datasources/entry")
     *
     * @Rest\QueryParam(name="source", nullable=true, description="source=integration/email/api")
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
     * @return mixed
     */
    public function postNewEntriesForMultipleDataSourcesAction(Request $request)
    {
        $sourceParam = $request->request->get('source', null);

        if ($sourceParam === null) {
            throw new BadRequestHttpException('Missing param "source"');
        }

        switch ($sourceParam) {
            case 'email':
            case 'integration':
                /* check if post new entry with json data */
                if (null !== $request->request->get('data', null)) {
                    return $this->processImportDataViaIntegration($request);
                }

                /* post new entry with file */
                return $this->processPostFileForMultipleDataSources($request, $sourceParam);

            case 'api':
                return $this->processImportDataViaApiKey($request);

            default:
                throw new BadRequestHttpException(sprintf('Not supported param "source" as %s', $sourceParam));
        }
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

    /**
     * @param FileBag $fileBag
     * @return array
     */
    private function getUploadedFiles(FileBag $fileBag)
    {
        $keys = $fileBag->keys();

        $files = array_map(
            function ($key) use ($fileBag) {
                /**@var UploadedFile $file */
                $file = $fileBag->get($key);
                return $file;
            }, $keys
        );

        if (!is_array($files)) {
            return [];
        }

        return array_values(
            array_filter(
                $files,
                function ($file) {
                    return ($file instanceof UploadedFile);
                }
            )
        );
    }

    /**
     * process uploaded files. The files may come from upload, fetcher(integration) module or email-hook module
     *
     * @param DataSourceInterface $dataSource
     * @param FileBag $fileBag
     * @param string $via is "upload" or "email" or "api" or "integration". Default is "upload"
     * @param bool $alsoMoveFile
     * @param null $metadata
     * @return array formatted as [ fileName => status, ... ]
     */
    private function processUploadedFiles(DataSourceInterface $dataSource, FileBag $fileBag, $via = DataSourceEntry::RECEIVED_VIA_UPLOAD, $alsoMoveFile = true, $metadata = null)
    {
        /** @var UploadedFile[] $files */
        $files = $this->getUploadedFiles($fileBag);

        $uploadRootDir = $this->container->getParameter('upload_file_dir');
        $dirItem = '/' . $dataSource->getPublisherId() . '/' . $dataSource->getId() . '/' . (date_create('today')->format('Ymd'));
        $uploadPath = $uploadRootDir . $dirItem;

        /** @var DataSourceEntryManagerInterface $dataSourceEntryManager */
        $dataSourceEntryManager = $this->get('ur.domain_manager.data_source_entry');

        $result = [];

        /**@var UploadedFile $file */
        foreach ($files as $file) {
            // sure correct file type
            if (!($file instanceof UploadedFile)) {
                continue;
            }

            try {
                $oneResult = $dataSourceEntryManager->uploadDataSourceEntryFile($file, $uploadPath, $dirItem, $dataSource, $via, $alsoMoveFile, $metadata);

                $result[] = $oneResult;
            } catch (Exception $e) {
                $originName = $file->getClientOriginalName();
                $oneResult = [
                    'file' => $originName,
                    'status' => false,
                    'message' => $e->getMessage()
                ];

                $result[] = $oneResult;
            }
        }

        return $result;
    }

    /**
     * process Post file for Multiple DataSources
     *
     * @param Request $request
     * @param string $source
     * @return array
     */
    private function processPostFileForMultipleDataSources(Request $request, $source = DataSourceEntry::RECEIVED_VIA_INTEGRATION)
    {
        $dataSourceIds = $request->request->get('ids', null);
        if (null === $dataSourceIds) {
            throw new BadRequestHttpException('expect ids is array of data source ids');
        }

        $dataSourceIds = json_decode($dataSourceIds, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dataSourceIds)) {
            throw new BadRequestHttpException('expect ids is array of data source ids');
        }

        /* "metadata": '{'from': 'uremail@mail.com', 'subject':'export data 02-12-1989', 'body':'dear Mr Thomas ...'}' */
        $metadata = $request->request->get('metadata', null);

        if ($metadata !== null) {
            $metadata = json_decode($metadata, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($metadata)) {
                throw new BadRequestHttpException('expect metadata is json format');
            }
        }

        /** @var DataSourceInterface $dataSource */
        $dataSources = array_map(function ($id) {
            return $this->one($id);
        }, $dataSourceIds);

        /** @var FileBag $fileBag */
        $fileBag = $request->files;

        $result = $this->processUploadedFilesForMultipleDataSources($dataSources, $fileBag, $source, $metadata);

        return $result;
    }

    /**
     * process uploaded files for multiple data sources. The files may come from upload, fetcher(integration) module or email-hook module
     *
     * @param array|DataSourceInterface[] $dataSources
     * @param FileBag $fileBag
     * @param string $source is "upload" or "email" or "api" or "integration". Default is "upload"
     * @param null $metadata
     * @return array formatted as [ dataSourceId => [ fileName => status, ... ], ... ]
     */
    private function processUploadedFilesForMultipleDataSources(array $dataSources, FileBag $fileBag, $source = DataSourceEntry::RECEIVED_VIA_INTEGRATION, $metadata = null)
    {
        $result = [];

        for ($i = 0, $len = count($dataSources); $i < $len; $i++) {
            $dataSource = $dataSources[$i];

            // IMPORTANT: copy original file for not last data source, and move original file for last data source
            $alsoMoveFile = ($i >= $len - 1);

            $oneResult = $this->processUploadedFiles($dataSource, $fileBag, $source, $alsoMoveFile, $metadata);
            $result[$dataSource->getId()] = $oneResult;
        }

        return $result;
    }

    private function processImportDataViaApiKey(Request $request)
    {
        $apiKey = $request->request->get('apiKey', null);

        if ($apiKey === null) {
            throw new BadRequestHttpException('apiKey must not be null');
        }

        $dataSourceManager = $this->get('ur.domain_manager.data_source');
        /** @var DataSourceInterface[] $dataSources */
        $dataSources = $dataSourceManager->getDataSourceByApiKey($apiKey);
        if (count($dataSources) === 0) {
            throw new BadRequestHttpException('cannot find any data source with this api key');
        }

        return $this->processUploadData($request, $dataSources[0], DataSourceEntryInterface::RECEIVED_VIA_API);
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws Exception
     */
    private function processImportDataViaIntegration(Request $request)
    {
        $id = $request->request->get('ids', null);

        /** @var DataSourceInterface $dataSource */
        $dataSource = $this->one($id);
        if (empty($dataSource)) {
            throw new BadRequestHttpException(sprintf('cannot find any data source with this id %d', $id));
        }

        return $this->processUploadData($request, $dataSource, DataSourceEntryInterface::RECEIVED_VIA_INTEGRATION);
    }

    /**
     * @param Request $request
     * @param DataSourceInterface $dataSource
     * @param string $sourceParam
     * @return mixed
     * @throws Exception
     */
    private function processUploadData(Request $request, DataSourceInterface $dataSource, $sourceParam = DataSourceEntryInterface::RECEIVED_VIA_API)
    {
        $data = $request->request->get('data', null);
        $data = json_encode($data);

        if (json_last_error() != JSON_ERROR_NONE) {
            throw new Exception('Json data error');
        }

        if (is_null($data)) {
            throw new BadRequestHttpException('data must not be null');
        }

        /**@var DataSourceInterface $dataSource */
        $uploadRootDir = $this->container->getParameter('upload_file_dir');
        $dirItem = '/' . $dataSource->getPublisher()->getId() . '/' . $dataSource->getId() . '/' . (date_create('today')->format('Ymd'));
        $uploadPath = $uploadRootDir . $dirItem;
        $name = '/data-message_' . round(microtime(true)) . '.json';
        $this->file_force_contents(substr($uploadPath, 1) . $name, $data);

        $file = new UploadedFile($uploadPath . $name, $name);
        $dataSourceEntryManager = $this->container->get('ur.domain_manager.data_source_entry');
        return $dataSourceEntryManager->uploadDataSourceEntryFile($file, $uploadPath, $dirItem, $dataSource, $sourceParam, false);
    }

    /**
     * @param $dir
     * @param $contents
     */
    private function file_force_contents($dir, $contents)
    {
        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach ($parts as $part)
            if (!is_dir($dir .= "/$part")) mkdir($dir);
        file_put_contents("$dir/$file", $contents);
    }
}