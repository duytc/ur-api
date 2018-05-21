<?php

namespace UR\Entity\Core;

use UR\Model\Core\ExchangeRate as ExchangeRateModel;

class ExchangeRate extends ExchangeRateModel
{
    protected $id;
    protected $rate;
    protected $fromCurrency;
    protected $toCurrency;
    protected $date;

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