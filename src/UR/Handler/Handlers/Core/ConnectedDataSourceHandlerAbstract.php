<?php

namespace UR\Handler\Handlers\Core;


use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Exception\InvalidFormException;
use UR\Exception\LogicException;
use UR\Form\Type\ConnectedDataSourceFormType;
use UR\Handler\RoleHandlerAbstract;
use UR\Model\ModelInterface;

abstract class ConnectedDataSourceHandlerAbstract extends RoleHandlerAbstract
{
    /**
     * @return ConnectedDataSourceManagerInterface
     */
    protected function getDomainManager()
    {
        return parent::getDomainManager();
    }

    /**
     * override
     * @inheritdoc
     */
    protected function processForm(ModelInterface $entity, array $parameters, $method = 'PUT')
    {
        if (!$this->supportsEntity($entity)) {
            throw new LogicException(sprintf('%s is not supported by this handler', get_class($entity)));
        }

        $formOptions = [
            'method' => $method,
        ];

        // backup entity as oldEntity before submit form
        $oldEntity = clone $entity;

        $form = $this->formFactory->create($this->getFormType(), $entity, $formOptions);

        $formConfig = $form->getConfig();

        if (!is_a($entity, $formConfig->getDataClass())) {
            throw new LogicException(sprintf('Form data class does not match entity returned from domain manager'));
        }

        // on any request except for PATCH, set any missing fields to null if they are missing from the request
        // this means that in a PUT request, if fields are missing, they are set to null overwriting the old values!
        $initializeMissingFields = 'PATCH' !== $method;

        $form->submit($parameters, $initializeMissingFields);

        if ($form->isValid()) {
            /** @var ConnectedDataSource $entity */
            $entity = $form->getData();

            $isDryRun = array_key_exists(ConnectedDataSourceFormType::IS_DRY_RUN, $parameters) ? (bool)$parameters[ConnectedDataSourceFormType::IS_DRY_RUN] : false;

            if (!$isDryRun) {
                $this->domainManager->save($entity);
            } else {
                $id = array_key_exists('connectedDataSourceId', $parameters) ? $parameters['connectedDataSourceId'] : null;
                $entity->setId($id);
            }

            return $entity;
        }

        throw new InvalidFormException('Invalid submitted data', $form);
    }
}