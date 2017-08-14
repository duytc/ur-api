<?php

namespace UR\Entity\Core;


use UR\Model\Core\TagInterface;
use UR\Model\Core\UserTag as UserTagModel;
use UR\Model\User\UserEntityInterface;

class UserTag extends UserTagModel
{
    /** @var  TagInterface */
    protected $tag;

    /**
     * @var UserEntityInterface
     */
    protected $publisher;

    /**
     * @inheritdoc
     *
     * inherit constructor for inheriting all default initialized value
     */
    public function __construct()
    {
        parent::__construct();
    }
}