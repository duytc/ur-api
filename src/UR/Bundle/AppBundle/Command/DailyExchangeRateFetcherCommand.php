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
use UR\Service\ExchangeRateService;

class DailyExchangeRateFetcherCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:daily-exchange-rate-fetcher';

    /** appId using to authenticated on API*/
    const APP_ID_DEFAULT = 'c87f920291ee4e8995db7e507c1f2d50';

    /** set configure this command*/
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption('appId', null, InputOption::VALUE_OPTIONAL, 'initialize app ID to get an accessible to OpenExchangeRate API', self::APP_ID_DEFAULT)
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
        /** @var ExchangeRateService $service */
        $service = $container->get('ur.service.exchange_rate');
        $this->io = new SymfonyStyle($input, $output);

        $appId = $input->getOption('appId');
        $currencies = $input->getOption('currencies');
        $date = $input->getOption('date');
        $service->setDate($date);
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
            $service->getAllCurrencies();
        }

        if(!empty($date)) {
            $this->io->text('date passing:' . $date);
            $data = $service->getExchangeRateHistorical($date);
        } else {
            $data = $service->getLatestExchangeRate();
        }

        if(!empty($data) && is_array($data)) {
            $result = $service->storeExchangeRate($data, $date ?: date("Y-m-d"));
            $this->io->section(sprintf('store latest exchange rate from %s to %s is ' .
                ($result ? 'successfully' : 'unsuccessfully') . ' or maybe the exchange rate is existed in system!',
                $service->getBaseCurrency(), $service->getToCurrency()));
        }
    }
}