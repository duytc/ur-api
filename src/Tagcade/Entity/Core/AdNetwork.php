<?php

namespace Tagcade\Entity\Core;

use Tagcade\Model\Core\AdNetwork as AdNetworkModel;

class AdNetwork extends AdNetworkModel
{
    protected $id;
    protected $publisher;
    protected $name;
    protected $url;

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