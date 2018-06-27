<?php

namespace UR\Model\Core;

class ExchangeRate implements ExchangeRateInterface
{
    protected $id;
    protected $rate;
    protected $fromCurrency;
    protected $toCurrency;
    protected $date;

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
     * @return mixed
     */
    public function getFromCurrency()
    {
        return $this->fromCurrency;
    }

    /**
     * @param mixed $code
     * @return $this
     */
    public function setFromCurrency($fromCurrency)
    {
        $this->fromCurrency = $fromCurrency;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getToCurrency()
    {
        return $this->toCurrency;
    }

    /**
     * @inheritdoc
     */
    public function setToCurrency($toCurrency)
    {
        $this->toCurrency = $toCurrency;
    }

    /**
     * @inheritdoc
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @inheritdoc
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @inheritdoc
     */
    public function getRate()
    {
        return $this->rate;
    }

    /**
     * @inheritdoc
     */
    public function setRate($rate)
    {
        $this->rate = $rate;
        
        return $this;
    }
}