<?php

namespace UR\Worker\Job\Concurrent;

use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use Swift_Mailer;
use Symfony\Component\Templating\EngineInterface;
use UR\Behaviors\OptimizationRuleUtilTrait;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;
use UR\Service\Import\CsvWriterInterface;
use UR\Service\LargeReport\RemoveOutOfDateReportServiceInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\Report\ReportBuilderInterface;

class ExportReportViewsAndSentEmail implements JobInterface
{
    use OptimizationRuleUtilTrait;

    const JOB_NAME = 'export_report_views_and_sent_email';
    const PARAMS = 'params';
    const USER_EMAILS = 'user_emails';
    const FILE_NAME = 'file_name';
    const PATH = 'path';
    const URL = 'url';
    const REPORT_VIEW_ID = '';
    const TOKEN = 'token';

    /**@var LoggerInterface */
    private $logger;

    /**@var ReportBuilderInterface */
    private $reportBuilder;

    /** @var ParamsBuilderInterface */
    private $paramsBuilder;

    /**@var CsvWriterInterface */
    private $csvWriter;

    /**@var Swift_Mailer */
    private $swiftMailer;
    /**
     * @var RemoveOutOfDateReportServiceInterface
     */
    private $removeOutOfDateReportService;

    /** @var string */
    private $sender;

    /** @var EngineInterface */
    private $templating;

    /**
     * @var ReportViewManagerInterface
     */
    private $reportViewManager;

    /**
     * @var \Swift_Transport
     */
    private $transport;

    /**
     * ProcessOptimizationFrequency constructor.
     * @param LoggerInterface $logger
     * @param ReportBuilderInterface $reportBuilder
     * @param ParamsBuilderInterface $paramsBuilder
     * @param CsvWriterInterface $csvWriter
     * @param Swift_Mailer $swiftMailer
     * @param EngineInterface $templating
     * @param RemoveOutOfDateReportServiceInterface $removeOutOfDateReportService
     * @param ReportViewManagerInterface $reportViewManager
     * @param \Swift_Transport $transport
     * @param string $sender
     */
    public function __construct(LoggerInterface $logger, ReportBuilderInterface $reportBuilder, ParamsBuilderInterface $paramsBuilder, CsvWriterInterface $csvWriter, Swift_Mailer $swiftMailer,
                                EngineInterface $templating, RemoveOutOfDateReportServiceInterface $removeOutOfDateReportService, ReportViewManagerInterface $reportViewManager, \Swift_Transport $transport, $sender)
    {

        $this->logger = $logger;
        $this->reportBuilder = $reportBuilder;
        $this->paramsBuilder = $paramsBuilder;
        $this->csvWriter = $csvWriter;
        $this->swiftMailer = $swiftMailer;
        $this->removeOutOfDateReportService = $removeOutOfDateReportService;
        $this->sender = $sender;
        $this->templating = $templating;
        $this->reportViewManager = $reportViewManager;
        $this->transport = $transport;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {
        $userEmails = $params->getRequiredParam(self::USER_EMAILS);
        $data = $params->getRequiredParam(self::PARAMS);
        $reportViewId = $params->getRequiredParam(self::REPORT_VIEW_ID);
        $token = $params->getRequiredParam(self::TOKEN);
        if ($reportViewId == null || $token == null) {
            $reportParams = $this->paramsBuilder->buildFromArray($data);
        } else {
            $reportView = $this->reportViewManager->find($reportViewId);
            if (!$reportView instanceof ReportViewInterface) {
                return;
            }
            $sharedKeysConfig = $reportView->getSharedKeysConfig();
            $shareConfig = $sharedKeysConfig[$token];
            $fieldsToBeShared = $shareConfig[ReportViewInterface::SHARE_FIELDS];
            $reportParams = $this->paramsBuilder->buildFromReportViewForSharedReport($reportView, $fieldsToBeShared, $data);
        }

        $reportParams->setNeedFormat(false);
        if (!$reportParams instanceof ParamsInterface) {
            return;
        }
        $reportResult = null;
        try {
            $reportResult = $this->reportBuilder->getReport($reportParams);
        } catch (Exception $exception) {
            $this->logger->error(sprintf('could not create alert, error occur: %s', $exception->getMessage()));

            return;
        }

        //dont need remove files on this, do it in command lines
        // Remove Out oft date Report
        //$this->removeOutOfDateReportService->removeOutOfDateReport();

        $path = $params->getRequiredParam(self::PATH);
        $url = $params->getRequiredParam(self::URL);

        $this->saveReportToFile($path, $reportResult);
        $this->sendEmailToUser($userEmails, $url);
    }

    /**
     * @param $path
     * @param ReportResultInterface $reportResult
     */
    private function saveReportToFile($path, ReportResultInterface $reportResult)
    {
        if (!is_file($path)) {
            //fopen and fpush csv will create file if not exist
            $myfile = fopen($path, "w");
            fputcsv($myfile, []);
        }

        // CSV Data
        $rows = new \SplDoublyLinkedList();
        foreach ($reportResult->getRows() as $row) {
            $line = [];
            foreach ($reportResult->getColumns() as $key => $value) {
                if (array_key_exists($key, $row)) {
                    $line[] = $row[$key];
                } else {
                    $line[] = null;
                }
            }
            $rows->push($line);
        }
        $reportCollection = new Collection($reportResult->getColumns(), $rows);
        $this->csvWriter->insertCollection($path, $reportCollection);
    }


    /**
     * @param $userEmails
     * @param $url
     * @throws Exception
     */
    private function sendEmailToUser($userEmails, $url)
    {
        $publisherName = 'Pubvantage Customer';
        $emailSubject = '[Pubvantage] Report is downloadable';
        $newEmailMessage = (new \Swift_Message())
            ->setFrom($this->sender)
            ->setTo(array_shift($userEmails))
            ->setBcc($userEmails)
            ->setSubject($emailSubject)
            ->setBody(
                $this->templating->render(
                    'URAppBundle:Report:email.html.twig',
                    ['publisher_name' => $publisherName, 'paths' => [$url]]
                ),
                'text/html');

        // send email
        try {
            $sendSuccessNumber = $this->swiftMailer->send($newEmailMessage);

            /** @var \Swift_Transport_SpoolTransport $transport */
            $transport = $this->swiftMailer->getTransport();
            if ($transport instanceof \Swift_Transport_SpoolTransport) {
                /** @var \Swift_Spool $spool */
                $spool = $transport->getSpool();
                $sendSuccessNumber = $spool->flushQueue($this->transport);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        if ($sendSuccessNumber > 0) {
            $this->logger->info(sprintf('sent %d emails to Publisher).', $sendSuccessNumber));
        }
    }
}