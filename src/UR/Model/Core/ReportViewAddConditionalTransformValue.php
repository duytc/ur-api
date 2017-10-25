<?php

namespace UR\Model\Core;

use UR\Model\User\Role\PublisherInterface;

class ReportViewAddConditionalTransformValue implements ReportViewAddConditionalTransformValueInterface
{
    protected $id;
    protected $name;
    protected $defaultValue;
    protected $sharedConditions;
    protected $conditions;
    protected $createdDate;

    /** @var PublisherInterface */
    protected $publisher;

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /**
     * @inheritdoc
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
    }

    /**
     * @inheritdoc
     */
    public function getSharedConditions()
    {
        return $this->sharedConditions;
    }

    /**
     * @inheritdoc
     */
    public function setSharedConditions($sharedConditions)
    {
        $this->sharedConditions = $sharedConditions;
    }

    /**
     * @inheritdoc
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * @inheritdoc
     */
    public function setConditions($conditions)
    {
        $this->conditions = $conditions;
    }

    /**
     * @inheritdoc
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
     */
    public function getPublisherId()
    {
        return $this->publisher->getId();
    }

    /**
     * @inheritdoc
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }
}