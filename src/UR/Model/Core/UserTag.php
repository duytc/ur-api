<?php

namespace UR\Model\Core;


use UR\Model\User\UserEntityInterface;

class UserTag implements UserTagInterface
{
    protected $id;

    /** @var  TagInterface */
    protected $tag;

    /**
     * @var UserEntityInterface
     */
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
    public function getPublisher()
    {
        return $this->publisher;
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
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @inheritdoc
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }
}