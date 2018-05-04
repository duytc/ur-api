<?php

namespace UR\Bundle\ApiBundle\Controller;

use DataDog\PagerBundle\Pagination;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;

use FOS\RestBundle\View\View;
use FOS\RestBundle\Util\Codes;

use UR\Handler\HandlerInterface;
use UR\Model\ModelInterface;
use UR\Exception\InvalidFormException;

use Symfony\Component\Form\FormTypeInterface;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use InvalidArgumentException;
use UR\Model\PagerParam;

abstract class RestControllerAbstract extends FOSRestController
{
    /**
     * @return ModelInterface[]
     */
    protected function all()
    {
        return $this->getHandler()->all();
    }

    /**
     * @param int $id
     * @return ModelInterface
     */
    protected function one($id)
    {
        $entity = $this->getOr404($id);
        $this->checkUserPermission($entity, 'view');

        return $entity;
    }

    /**
     * @param Request $request
     * @return FormTypeInterface|View
     */
    protected function post(Request $request)
    {
        try {
            $newEntity = $this->getHandler()->post(
                $request->request->all()
            );

            $routeOptions = array(
                '_format' => $request->get('_format')
            );

            return $this->addRedirectToResource($newEntity, Codes::HTTP_CREATED, $routeOptions);
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * @param Request $request
     * @return array|View|null
     */
    protected function postAndReturnEntityData(Request $request)
    {
        try {
            $newEntity = $this->getHandler()->post(
                $request->request->all()
            );

            $routeOptions = array(
                '_format' => $request->get('_format')
            );

            return $this->view($newEntity, Codes::HTTP_CREATED, $routeOptions);

        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * @param Request $request
     * @param $message
     * @return View
     */
    protected function responseAfterValidate(Request $request, $message)
    {
        $routeOptions = array(
            '_format' => $request->get('_format')
        );

        return $this->view($message, Codes::HTTP_NOT_IMPLEMENTED, $routeOptions);
    }

    /**
     * @param Request $request
     * @param int $id
     * @return FormTypeInterface|View
     */
    protected function put(Request $request, $id)
    {
        try {
            if (!($entity = $this->getHandler()->get($id))) {
                // create new
                $statusCode = Codes::HTTP_CREATED;
                $entity = $this->getHandler()->post(
                    $request->request->all()
                );
            } else {
                $this->checkUserPermission($entity, 'edit');
                $statusCode = Codes::HTTP_NO_CONTENT;
                $entity = $this->getHandler()->put(
                    $entity,
                    $request->request->all()
                );
            }

            $routeOptions = array(
                '_format' => $request->get('_format')
            );

            return $this->addRedirectToResource($entity, $statusCode, $routeOptions);
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * @param Request $request
     * @param int $id
     * @return FormTypeInterface|View
     */
    protected function patch(Request $request, $id)
    {
        try {
            $entity = $this->getOr404($id);
            $this->checkUserPermission($entity, 'edit');

            $entity = $this->getHandler()->patch(
                $entity,
                $request->request->all()
            );

            $routeOptions = array(
                '_format' => $request->get('_format')
            );

            return $this->addRedirectToResource($entity, Codes::HTTP_NO_CONTENT, $routeOptions);
        } catch (InvalidFormException $exception) {
            return $exception->getForm();
        }
    }

    /**
     * @param int $id
     * @return View
     */
    protected function delete($id)
    {
        $entity = $this->getOr404($id);
        $this->checkUserPermission($entity, 'edit');

        $this->getHandler()->delete($entity);

        $view = $this->view(null, Codes::HTTP_NO_CONTENT);

        return $this->handleView($view);
    }

    /**
     * @param int $id
     * @return ModelInterface
     * @throws NotFoundHttpException
     */
    protected function getOr404($id)
    {
        if (!($entity = $this->getHandler()->get($id))) {
            throw new NotFoundHttpException(
                sprintf("The %s resource '%s' was not found or you do not have access", $this->getResourceName(), $id)
            );
        }

        return $entity;
    }

    /**
     * By default this does not cause a redirection unless force_redirects is true
     * It just sends the url as part of the response, the client decides what to do next
     *
     * @param int|ModelInterface $id
     * @param $statusCode
     * @param array $routeOptions
     * @return View
     */
    protected function addRedirectToResource($id, $statusCode, array $routeOptions)
    {
        if (is_array($id)) {

        }

        if ($id instanceof ModelInterface) {
            $id = $id->getId();
        }

        $routeOptions += array(
            '_format' => 'json'
        );

        $routeOptions['id'] = $id;

        return $this->routeRedirectView($this->getGETRouteName(), $routeOptions, $statusCode);
    }

    /**
     * @return string
     */
    abstract protected function getResourceName();

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    abstract protected function getGETRouteName();

    /**
     * @return HandlerInterface
     */
    abstract protected function getHandler();

    /**
     * @param ModelInterface|ModelInterface[] $entity The entity instance
     * @param string $permission
     * @return bool
     * @throws InvalidArgumentException if you pass an unknown permission
     * @throws AccessDeniedException
     */
    protected function checkUserPermission($entity, $permission = 'view')
    {
        $toCheckEntities = [];
        if ($entity instanceof ModelInterface) {
            $toCheckEntities[] = $entity;
        } else if (is_array($entity)) {
            $toCheckEntities = $entity;
        } else {
            throw new \InvalidArgumentException('Expect argument to be ModelInterface or array of ModelInterface');
        }

        foreach ($toCheckEntities as $item) {
            if (!$item instanceof ModelInterface) {
                throw new \InvalidArgumentException('Expect Entity Object and implement ModelInterface');
            }

            $this->checkUserPermissionForSingleEntity($item, $permission);
        }

        return true;
    }

    protected function checkUserPermissionForSingleEntity(ModelInterface $entity, $permission)
    {
        if (!in_array($permission, ['view', 'edit'])) {
            throw new InvalidArgumentException('checking for an invalid permission');
        }

        $securityContext = $this->get('security.helper');

        // allow admins to everything
        if ($securityContext->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // check voters
        if (false === $securityContext->isGranted($permission, $entity)) {
            throw new AccessDeniedException(
                sprintf(
                    'You do not have permission to %s this %s or it does not exist',
                    $permission,
                    $this->getResourceName()
                )
            );
        }

        return true;
    }

    /**
     * @var array $params
     * @return PagerParam
     */
    protected function _createParams(array $params)
    {
        // create a params array with all values set to null
        $defaultParams = array_fill_keys([
            PagerParam::PARAM_SEARCH_FIELD,
            PagerParam::PARAM_SEARCH_KEY,
            PagerParam::PARAM_SORT_FIELD,
            PagerParam::PARAM_SORT_DIRECTION,
            PagerParam::PARAM_PUBLISHER_ID,
            PagerParam::PARAM_PAGE,
            PagerParam::PARAM_LIMIT,
        ], null);

        $params = array_merge($defaultParams, $params);
        $publisherId = intval($params[PagerParam::PARAM_PUBLISHER_ID]);
        return new PagerParam($params[PagerParam::PARAM_SEARCH_FIELD], $params[PagerParam::PARAM_SEARCH_KEY], $params[PagerParam::PARAM_SORT_FIELD], $params[PagerParam::PARAM_SORT_DIRECTION], $publisherId, $params[PagerParam::PARAM_PAGE], $params[PagerParam::PARAM_LIMIT]);
    }

    /**
     * @return PagerParam
     */
    protected function getParams()
    {
        $params = $this->get('fos_rest.request.param_fetcher')->all($strict = true);
        return $this->_createParams($params);
    }

    protected function getPagination(QueryBuilder $qb, Request $request)
    {
        $pagination = new Pagination($qb, $request);
        return array(
            'totalRecord' => $pagination->total(),
            'records' => $pagination->getArrayCopy(),
            'itemPerPage' => $pagination->itemsPerPage(),
            'currentPage' => $pagination->currentPage()
        );
    }
}
