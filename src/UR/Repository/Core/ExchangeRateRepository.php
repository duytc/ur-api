<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;

class ExchangeRateRepository extends EntityRepository implements ExchangeRateRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'date' => 'date', 'fromCurrency' => 'fromCurrency', 'toCurrency' => 'toCurrency'];
}