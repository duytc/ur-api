<?php

namespace UR\Service;

use Doctrine\ORM\ORMException;
use UR\Entity\Core\ExchangeRate;
use Symfony\Component\Process\Exception\RuntimeException;
use UR\DomainManager\ExchangeRateManagerInterface;
use UR\Repository\Core\ExchangeRateRepositoryInterface;


class ExchangeRateService
{
    /** appId using to authenticated on API*/
    const APP_ID_DEFAULT = 'c87f920291ee4e8995db7e507c1f2d50';
    const API_BASE_PATH = 'https://openexchangerates.org/api/';

    /** all of endpoint are available on API get Exchange Rate*/
    const LATEST_DATA = 'latest.json';
    const CURRENCY_DATA = 'currencies.json';
    const HISTORICAL = 'historical';

    private
        $appId,
        $conversion,
        $toCurrency = 'EUR',
        $baseCurrency = 'USD',
        $exchangeRate,
        $currencyList = array(),
        $exchangeRateHistoricalList = array();

    function __construct(ExchangeRateManagerInterface $exchangeRateManager, ExchangeRateRepositoryInterface $exchangeRateRepository) {
        $this->exchangeRateManager = $exchangeRateManager;
        $this->exchangeRateRepository = $exchangeRateRepository;
    }

    public function setBaseCurrency($currency = 'USD')
    {
        $this->baseCurrency = $currency;
    }

    public function getBaseCurrency()
    {
        return $this->baseCurrency;
    }

    public function setToCurrency($toCurrency = 'EUR')
    {
        $this->toCurrency = $toCurrency;
    }

    public function getToCurrency()
    {
        return $this->toCurrency;
    }

    public function setExchangeRate(ExchangeRate $exchangeRate)
    {
        $this->exchangeRate = $exchangeRate;
    }

    public function getExchangeRate()
    {
        return $this->exchangeRate;
    }

    public function setConversion($conversion = 'USD-EUR')
    {
        $this->conversion = $conversion;
    }

    public function getConversion()
    {
        return $this->conversion;
    }

    public function setAppID($appId = '')
    {
        if(!$appId)
            return;

        $this->appId = $appId;
    }

    public function getAppId()
    {
        return $this->appId ?: self::APP_ID_DEFAULT;
    }

    protected function refactorDataResponse($endPoint = '')
    {
        return $this->getMsgByEndPoint($endPoint);
    }

    protected function getMsgByEndPoint($endPoint = '')
    {
        switch ($endPoint)
        {
            case self::LATEST_DATA:
                return sprintf('Get latest exchange rate data successfully!', '');
            case self::CURRENCY_DATA:
                return sprintf('Get currencies list successfully!', '');

            case self::HISTORICAL:
                return sprintf('Get exchange rate data historical successfully!', '');

            default:
                return sprintf('Get data Successfully!', '');
        }
    }

    protected  function buildQuery(array $data)
    {
        return http_build_query($data);
    }

    protected  function refactorResponse($response = null)
    {
        return !is_null($response) ? json_decode($response, true) : null;
    }

    public function getExchangeRateHistorical($date)
    {
        $res = $this->sendGetRequest($this->getHistoricalPathByDate($date));
        return $res && is_array($res) && array_key_exists('rates', $res) ? $res['rates'] : [];
    }

    public function getAllCurrencies()
    {
        return $this->sendGetRequest(self::CURRENCY_DATA);
    }

    public function getLatestExchangeRate()
    {
        $res = $this->sendGetRequest(self::LATEST_DATA);
        return $res && is_array($res) && array_key_exists('rates', $res) ? $res['rates'] : [];
    }

    /**
     * Logging error & status code when the response contain errors
     * return empty array data
     * @param array $res
     * @return array empty
     */
    public function handleError($res)
    {
        throw new RuntimeException(sprintf('An error occurred during get Exchange Rate: %s',
            (is_array($res) ? 'code ' . ($res['status'] ?: '') . ($res['description'] ?: '') : '')));
        /*$this->io->warning(sprintf('An error occurred during get Exchange Rate: %s',
            (is_array($res) ? 'code ' . ($res['status'] ?: '') . ($res['description'] ?: '') : '')));*/
    }

    public  function getHistoricalPathByDate($date = null)
    {
        return self::HISTORICAL . '/' . ($date ?: date("Y-m-d")) . '.json';
    }

    /**
     * send a request to OpenExchangeRate API
     * to get conversion, currencies list, etc......
     * @param $endPoint
     * @return array $responseFetcher
     */
    public function sendGetRequest($endPoint = '')
    {
        $url = self::API_BASE_PATH . $endPoint . '?' . $this->buildQuery([
                'app_id' => $this->getAppId(),
                'base' => $this->getBaseCurrency(),
                //'callback' => $this->refactorDataResponse($endPoint)
            ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $responseFetcher = trim(curl_exec($ch));
        
        if (false === $responseFetcher || empty($responseFetcher)) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException(sprintf('An error occurred: %s.', $error));
        }

        curl_close($ch);

        if($responseFetcher) {
            $responseFetcher = $this->refactorResponse($responseFetcher);
        }

        if(is_array($responseFetcher) && array_key_exists('error', $responseFetcher) && $responseFetcher['error']) {
            return $this->handleError($responseFetcher);
        }

        return $responseFetcher;
    }

    /**
     *
     * Stores all exchange rate data to Databse through ExchangeRateManger/Doctrine
     * @param array $data
     * @return mixed
     *
     */
    public function storeExchangeRate(array $data = [], String $date = '')
    {
        $isSuccess = true;
        try {
            $exchangeRate = new ExchangeRate();
            $exchangeRate->setDate($date ?: date('Y-m-d'));
            $exchangeRate->setFromCurrency($this->getBaseCurrency() ?: 'USD');
            $exchangeRate->setToCurrency($this->getToCurrency() ?: 'EUR');
            $exchangeRate->setRate($data[$this->getToCurrency() ?: 'EUR']);

            if(empty($this->exchangeRateRepository->findBy(array(
                'date' => $date ?: date('Y-m-d'),
                'fromCurrency' => $this->getBaseCurrency(),
                'toCurrency' => $this->getToCurrency()
            )))) {
                $this->exchangeRateManager->save($exchangeRate);
                $this->setExchangeRate($exchangeRate);
            } else {
                $isSuccess = false;
                //do nothing if we've stored currency conversion in our Database
                //$this->exchangeRateManager->save($exchangeRate);
            }
        } catch (\Doctrine\ORM\ORMException $e) {
            throw new ORMException($e->getMessage());
        }

        return $isSuccess;
    }
}