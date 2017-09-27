<?php

namespace UR\Bundle\AppBundle\Command;

use Swift_Mailer;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\AlertRepositoryInterface;
use UR\Service\StringUtilTrait;

class SendEmailWarningForAlertCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var PublisherManagerInterface
     */
    private $publisherManager;

    /**
     * @var AlertManagerInterface
     */
    private $alertManager;

    /**
     * @var AlertRepositoryInterface
     */
    private $alertRepository;

    /**
     * @var Swift_Mailer
     */
    private $swiftMailer;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:alert:email-warning:send')
            ->setDescription('Send email alert for critical alerts for Publisher');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');

        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $this->alertManager = $container->get('ur.domain_manager.alert');
        $this->alertRepository = $container->get('ur.repository.alert');
        $this->swiftMailer = $container->get('swiftmailer.mailer');

        $publishers = $this->publisherManager->allActivePublishers();
        $output->writeln(sprintf('sending alert Emails for %d publishers', count($publishers)));

        // send Email Alert
        $emailAlertsCount = $this->sendEmailAlertForPublisher($output, $publishers);

        $output->writeln(sprintf('command run successfully: emails sent to %d Publisher.', $emailAlertsCount));
    }

    /**
     * send Email Alert for all Publishers
     *
     * @param OutputInterface $output
     * @param array|PublisherInterface[] $publishers
     * @return int migrated update alert type count
     */
    private function sendEmailAlertForPublisher(OutputInterface $output, array $publishers)
    {
        $emailAlertsCount = 0;

        foreach ($publishers as $publisher) {
            if (!$publisher instanceof PublisherInterface) {
                continue;
            }

            $emails = $publisher->getEmailSendAlert();
            if (!is_array($emails) || empty($emails)) {
                $output->writeln(sprintf('[warning] Publisher %s (id:%d) missing email to send alert. Please set email for this account then run again.', $this->getPublisherName($publisher), $publisher->getId()));
                continue;
            }

            $alerts = $this->alertRepository->getAlertsToSendEmailByTypesQuery($publisher, [AlertInterface::ALERT_TYPE_WARNING]);
            if (!is_array($alerts)) {
                continue;
            }

            foreach ($emails as $email) {
                if (!is_array($email) || !array_key_exists('email', $email)) {
                    continue;
                }

                $sentEmailsNumber = $this->sendEmailAlert($output, $publisher, $alerts, $email['email']);
                if ($sentEmailsNumber > 0) {
                    $emailAlertsCount++;
                }
            }
        }

        return $emailAlertsCount;
    }

    /**
     * send email alert to an email
     *
     * @param OutputInterface $output
     * @param PublisherInterface $publisher
     * @param array $alerts
     * @param string $email
     * @return int number of sent emails
     */
    private function sendEmailAlert(OutputInterface $output, PublisherInterface $publisher, array $alerts, $email)
    {
        if (empty($alerts)) {
            return 0;
        }

        // create email, rendering from a template:
        // src/UR/Bundle/AppBundle/Resources/views/Alert/email.html.twig
        $sender = $this->getContainer()->getParameter('mailer_sender');
        $emailSubject = '[Pubvantage] Email notify alert on Pubvantage System';
        $publisherName = $this->getPublisherName($publisher);
        $alertDetails = $this->getAlertDetails($alerts);

        $newEmailMessage = (new \Swift_Message())
            ->setFrom($sender)
            ->setTo($email)
            ->setSubject($emailSubject)
            ->setBody(
                $this->getContainer()->get('templating')->render(
                    'URAppBundle:Alert:email.html.twig',
                    ['publisher_name' => $publisherName, 'alert_details' => $alertDetails]
                ),
                'text/html');

        // send email
        $sendSuccessNumber = $this->swiftMailer->send($newEmailMessage);

        // update isSent to true for all alerts have been sent email
        if ($sendSuccessNumber > 0) {
            $output->writeln(sprintf('sent %d emails to Publisher %s (id:%d).', $sendSuccessNumber, $publisherName, $publisher->getId()));

            foreach ($alerts as $alert) {
                if (!$alert instanceof AlertInterface) {
                    continue;
                }

                $alert->setIsSent(true);
                $this->alertManager->save($alert);
            }
        }

        return $sendSuccessNumber;
    }

    /**
     * get Publisher Name
     *
     * @param PublisherInterface $publisher
     * @return string
     */
    private function getPublisherName(PublisherInterface $publisher)
    {
        return $publisher->getFirstName() . ' ' . $publisher->getLastName();
    }

    /**
     * get Alert Details
     *
     * @param array $alerts
     * @return array
     */
    private function getAlertDetails(array $alerts)
    {
        $alertDetails = [];

        foreach ($alerts as $alert) {
            if (!$alert instanceof AlertInterface) {
                continue;
            }

            $alertDetails[] = $this->fillEmailBody($alert);
        }

        return $alertDetails;
    }

    /**
     * @param AlertInterface $alert
     * @return string
     */
    private function fillEmailBody(AlertInterface $alert)
    {
        $date = date_format($alert->getCreatedDate(), 'Y-m-d H:i:s');
        switch ($alert->getCode()) {
            case AlertInterface::ALERT_CODE_DATA_SOURCE_NO_DATA_RECEIVED_DAILY:
                return sprintf('No files have been uploaded to data source "%s" (ID: "%s"). You have an alert configured if no files are uploaded. Date "%s"', $alert->getDataSource()->getName(), $alert->getDataSource()->getId(), $date);

            case AlertInterface::ALERT_CODE_BROWSER_AUTOMATION_PASSWORD_EXPIRY:
                $detail = $alert->getDetail();
                $username = $detail['username'];
                $url = $detail['url'];
                return sprintf('Password expires on data source "%s".  Please change password for account "%s" on link "%s"  or contact your account manager. Date "%s" ', $alert->getDataSource()->getId(), $username, $url, $date);

            default:
                return 'Unknown alert code . Please contact your account manager';
        }
    }
}