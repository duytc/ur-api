<?php
/**
 * Created by PhpStorm.
 * User: NamDN
 * Date: 5/15/18
 * Time: 5:35 PM
 */

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ExchangeRateManagerInterface;
use UR\Entity\Core\ExchangeRate;
use Monolog\Logger;
use UR\Model\Core\ExchangeRateInterface;
use UR\Repository\Core\ExchangeRateRepositoryInterface;
use UR\Service\ExchangeRateService;

class DailyExchangeRateFetcherCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:daily-exchange-rate-fetcher';

    /** appId using to authenticated on API*/
    const APP_ID_DEFAULT = 'c87f920291ee4e8995db7e507c1f2d50';
    /*const API_BASE_PATH = 'https://openexchangerates.org/api/';*/

    /** all of endpoint are available on API get Exchange Rate*/
    /*const LATEST_DATA = 'latest.json';
    const CURRENCY_DATA = 'currencies.json';
    const HISTORICAL = 'historical';

    private
        $appId,
        $conversion,
        $toCurrency = 'EUR',
        $baseCurrency = 'USD',
        $currencyList = array(),
        $exchangeRateHistoricalList = array(),
        $exchangeRateManager, $exchangeRateRepository;*/

    /** set configure this command*/
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption('appId', null, InputOption::VALUE_OPTIONAL, 'initialize app ID to get an accessible to OpenExchangeRate API', self::APP_ID_DEFAULT)
            //->addOption('baseCurrency', null, InputOption::VALUE_OPTIONAL, 'set base currency to get conversion for it', 'USD')
            //->addOption('toCurrency', null, InputOption::VALUE_OPTIONAL, 'set dist currency to get conversion to it', 'EUR')
            ->addOption('conversion', null, InputOption::VALUE_OPTIONAL, 'set conversion for these currencies, e.g: USD-EUR (get conversion from USD to EUR)', 'USD-EUR')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'using to get exchange rate by date that provided,  format : YYYY-MM-DD', date('Y-m-d'))
            ->addOption('currencies', null, InputOption::VALUE_OPTIONAL, 'get all currencies are available on API', false)
            ->setDescription('get daily exchange rate for currency conversion');
    }

    /**
     * Run execute this command triggered by an event, listener, or console
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->exchangeRateManager = $container->get('ur.domain_manager.exchange_rate');
        $this->exchangeRateRepository = $container->get('ur.repository.exchange_rate');
        $service = new ExchangeRateService($this->exchangeRateManager, $this->exchangeRateRepository);
        $this->io = new SymfonyStyle($input, $output);

        $appId = $input->getOption('appId');
        $currencies = $input->getOption('currencies');
        $date = $input->getOption('date');
        list($baseCurrency, $toCurrency) = preg_split("/[-]/", $input->getOption('conversion') ?: $this->getConversion());
        if($appId && !preg_match('/\b'.self::APP_ID_DEFAULT.'\b/', $appId))
            $service->setAppID($appId);

        if(!empty($baseCurrency)) {
            $service->setBaseCurrency($baseCurrency);
        }

        if(!empty($toCurrency)) {
            $service->setToCurrency($toCurrency);
        }

        if(!empty($currencies)) {
            $this->io->text('get all currencies:' . $currencies);
            $this->currencyList = $service->getAllCurrencies();
            $this->io->text('get all currencies:' . var_dump($this->currencyList));
        }

        if(!empty($date)) {
            $this->io->text('date passing:' . $date);
            $data = $this->exchangeRateHistoricalList = $service->getExchangeRateHistorical($date);
            $this->io->text('get exchange by date:' . $date . var_dump($this->exchangeRateHistoricalList));
        } else {
            $data = $service->getLatestExchangeRate();
            $this->io->section(sprintf('get latest exchange rate %s', var_dump($data)));
        }

        if(!empty($data) && is_array($data)) {
            $result = $service->storeExchangeRate($data, $date ?: date("Y-m-d"));
            $this->io->section(sprintf('store latest exchange rate from %s to %s is ' .
                ($result ? 'successfully' : 'unsuccessfully') . ' or maybe the exchange rate is existed in system!',
                $service->getBaseCurrency(), $service->getToCurrency()));
        }
    }

    /*protected  function getHistoricalPathByDate($date = null)
    {
        return self::HISTORICAL . '/' . ($date ?: date("Y-m-d")) . '.json';
    }

    public function getExchangeRateHistorical($date)
    {
        $res = $this->sendGetRequest($this->getHistoricalPathByDate($date));
        return $res && is_array($res) && array_key_exists('rates', $res) ? $res['rates'] : [];
    }

    protected function getAllCurrencies()
    {
        return $this->sendGetRequest(self::CURRENCY_DATA);
    }

    protected function getLatestExchangeRate()
    {
        $res = $this->sendGetRequest(self::LATEST_DATA);
        return $res && is_array($res) && array_key_exists('rates', $res) ? $res['rates'] : [];
    }*/

    /**
     * send a request to OpenExchangeRate API
     * to get conversion, currencies list, etc......
     * @param $endPoint
     * @return array $responseFetcher
     */
    /*protected function sendGetRequest($endPoint = '')
    {
        $url = self::API_BASE_PATH . $endPoint . '?' . $this->buildQuery([
                'app_id' => $this->getAppId(),
                'base' => $this->getBaseCurrency(),
                'callback' => $this->refactorDataResponse($endPoint)
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

        if (false === $responseFetcher) {
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
    }*/

    /*protected function getMsgByEndPoint($endPoint = '')
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

    protected function refactorDataResponse($endPoint = '')
    {
        $this->io->section($this->getMsgByEndPoint($endPoint));
    }

    protected function setBaseCurrency($currency = 'USD')
    {
        $this->baseCurrency = $currency;
    }

    protected function getBaseCurrency()
    {
        return $this->baseCurrency;
    }

    protected function setToCurrency($toCurrency = 'EUR')
    {
        $this->toCurrency = $toCurrency;
    }

    protected function getToCurrency()
    {
        return $this->toCurrency;
    }

    protected function setConversion($conversion = 'USD-EUR')
    {
        $this->conversion = $conversion;
    }

    protected function getConversion()
    {
        return $this->conversion;
    }

    protected function setAppID($appId = '')
    {
        if(!$appId)
            return;

        $this->appId = $appId;
    }

    protected function getAppId()
    {
        return $this->appId ?: self::APP_ID_DEFAULT;
    }

    protected  function refactorResponse($response = null)
    {
        return !is_null($response) ? json_decode($response, true) : null;
    }*/

    /**
     * Logging error & status code when the response contain errors
     * return empty array data
     * @param array $res
     * @return array empty
     */
    /*protected function handleError($res)
    {
        $this->io->warning(sprintf('An error occurred during get Exchange Rate: %s',
            (is_array($res) ? 'code ' . ($res['status'] ?: '') . ($res['description'] ?: '') : '')));

        return [];
    }*/

    /**
     *
     * Stores all exchange rate data to Databse through ExchangeRateManger/Doctrine
     * @param array $data
     * @return mixed
     *
     */
    protected function storeExchangeRate(array $data = [], String $date = '')
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
            } else {
                $isSuccess = false;
                //do nothing if we've stored currency conversion in our Database
                //$this->exchangeRateManager->save($exchangeRate);
            }
        } catch (\Doctrine\ORM\ORMException $e) {
            /** @var Logger $logger */
            $this->logger = $this->getContainer()->get('logger');
            $this->logger->warning($e->getMessage());
            $isSuccess = false;
        }

        return $isSuccess;
    }
}