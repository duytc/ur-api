<?php

namespace UR\Entity\Core;


use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;
use UR\Model\Core\IntegrationTag as IntegrationTagModel;

class IntegrationTag extends IntegrationTagModel
{
    /** @var  TagInterface */
    protected $tag;

    /**
     * @var IntegrationInterface
     */
    protected $integration;

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