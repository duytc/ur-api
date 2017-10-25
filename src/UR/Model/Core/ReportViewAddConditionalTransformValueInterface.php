<?php

namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface ReportViewAddConditionalTransformValueInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return mixed
     */
    public function getName();

    /**
     * @param mixed $name
     */
    public function setName($name);

    /**
     * @return mixed
     */
    public function getDefaultValue();

    /**
     * @param mixed $defaultValue
     */
    public function setDefaultValue($defaultValue);

    /**
     * @return array
     */
    public function getSharedConditions();

    /**
     * @param array $shareConditions
     */
    public function setSharedConditions($shareConditions);

    /**
     * @return array
     */
    public function getConditions();

    /**
     * @param array $conditions
     */
    public function setConditions($conditions);

    /**
     * @return mixed
     */
    public function getPublisher();

    /**
     * @return int
     */
    public function getPublisherId();

    /**
     * @param mixed $publisher
     * @return $this
     */
    public function setPublisher($publisher);

    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);
}