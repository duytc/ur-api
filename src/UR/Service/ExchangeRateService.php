<?php

namespace UR\Service;

use Doctrine\ORM\ORMException;
use UR\Entity\Core\ExchangeRate;
use UR\Model\Core\ExchangeRateInterface;
use UR\DomainManager\ExchangeRateManagerInterface;
use Symfony\Component\Process\Exception\RuntimeException;


class ExchangeRateService
{
    /** Base currency of FreePlan*/
    const DEFAULT_CURRENCY = 'USD';
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
        $date = null;

    function __construct(ExchangeRateManagerInterface $exchangeRateManager) {
        $this->exchangeRateManager = $exchangeRateManager;
    }

    public function setDate($date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
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
    }

    public  function getHistoricalPathByDate($date = null)
    {
        return self::HISTORICAL . '/' . ($date ?: date("Y-m-d")) . '.json';
    }

    /**
     * check whether currency param is available supported or not
     *
     * @return boolean
     */
    protected function isBaseCurrencyNotSupported()
    {
        return $this->getBaseCurrency() !== self::DEFAULT_CURRENCY;
    }

    protected function getReverseRate(array $rates = [], String $toCurrency = self::DEFAULT_CURRENCY)
    {
        return $rates[$toCurrency] > 0 ? 1 / $rates[$toCurrency] : 1;
    }

    /**
     * re-calculate exchange rate if base currency was passed is not support in OpenExchangeRate API
     * @return array $exchangeRate
     */
    protected final function calcExchangeRateOutOfFreePlan()
    {
        $currentBaseCurrency = $this->getBaseCurrency();
        $this->setBaseCurrency(self::DEFAULT_CURRENCY);
        $transformExchangeRate = is_null($this->getDate()) ? $this->getLatestExchangeRate() : $this->getExchangeRateHistorical($this->getDate());
        $this->setBaseCurrency($currentBaseCurrency);

        return [ "rates" => [
                    self::DEFAULT_CURRENCY => (array_key_exists($currentBaseCurrency, $transformExchangeRate) ?
                        $this->getReverseRate($transformExchangeRate, $currentBaseCurrency) : 1)
            ]
        ];
    }

    /**
     * send a request to OpenExchangeRate API
     * to get conversion, currencies list, etc......
     * @param $endPoint
     * @return array $responseFetcher
     */
    public function sendGetRequest($endPoint = '')
    {
        //check if base currency is out of free plan
        if($this->isBaseCurrencyNotSupported()) {
            return $this->calcExchangeRateOutOfFreePlan();
        }


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
     * @param String $date
     * @return mixed
     * @throws ORMException
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

            if(empty($this->exchangeRateManager->findBy(array(
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

    /**
     * Get the conversion in our system
     * Default get the conversion from USD-EUR with current moment
     * If the date passed in service is not valid in our system, try to get
     * yesterday closing exchange rate
     *
     * @param String $date The date to get the conversion for currency
     * @param String $fromCurrency The base currency to get exchange rate for it
     * @param String $toCurrency The destination that need to transform
     *
     * @return ExchangeRateInterface $exchangeRate
     * @throws ORMException
     *
     */
    public function getConversionByDate($date = null, $fromCurrency = 'USD', $toCurrency = 'EUR')
    {
        $date = $date ?: date('Y-m-d');
        $exchangeRate = null;

        try {
            $data = $this->exchangeRateManager->findBy([
                'date' => $date,
                'fromCurrency' => $fromCurrency,
                'toCurrency' => $toCurrency
            ]) ?: $this->exchangeRateManager->findBy([
                'fromCurrency' => $fromCurrency,
                'toCurrency' => $toCurrency
            ], array('date' => 'desc'));

            if(is_array($data) && !empty($data)) {
                $exchangeRate = $data[0] && $data[0] instanceof ExchangeRateInterface ? $data[0] : null;
            } else {
                //call Exchange Rate Service to get conversion via API call
                $exRate = $this->getExchangeRateHistorical($date);
                if(!empty($exRate)) {
                    $this->storeExchangeRate($exRate);
                    $exchangeRate = $this->getExchangeRate();
                }
            }
        } catch(\Doctrine\ORM\ORMException $orm) {
            throw new ORMException($orm->getCode() . ':' . $orm->getMessage());
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $exchangeRate;
    }
}