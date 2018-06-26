<?php

namespace UR\Bundle\AppBundle\Command;

use DateTime;
use Swift_Mailer;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\AlertRepositoryInterface;
use UR\Service\StringUtilTrait;

class SendEmailReminderForActionRequiredAlertCommand extends ContainerAwareCommand
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
            ->setName('ur:action-required-alert:email-reminder:send')
            ->setDescription('Send email reminder for actionRequired alerts to Publisher');
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
        $output->writeln(sprintf('Sending Reminder Emails for %d publishers', count($publishers)));

        // send Email Alert
        $sendEmailToPubCount  = $this->sendEmailReminderForActionRequiredToPublisher($output, $publishers);

        $this->logger->info(sprintf('Command run successfully: emails has been sent to %d Publisher.', $sendEmailToPubCount));
    }

    /**
     * send Email Alert for all Publishers
     *
     * @param OutputInterface $output
     * @param array|PublisherInterface[] $publishers
     * @return int migrated update alert type count
     */
    private function sendEmailReminderForActionRequiredToPublisher(OutputInterface $output, array $publishers)
    {
        $emailRemindersCount = 0;
        $sendEmailToPubCount = 0;
        foreach ($publishers as $publisher) {
            if (!$publisher instanceof PublisherInterface) {
                continue;
            }

            $emails = $publisher->getEmailSendAlert();
            if (!is_array($emails) || empty($emails)) {
                $output->writeln(sprintf('----------------*************-------------------', $this->getPublisherName($publisher), $publisher->getId()));
                $output->writeln(sprintf('[warning] Publisher %s (id:%d) missing email to send alert. Please set email for this account then run again.', $this->getPublisherName($publisher), $publisher->getId()));
                continue;
            }

            // get actionRequired Alert that it has not been confirmed from publisher
            $alerts = $this->alertRepository->getAlertsToSendEmailByTypesQuery($publisher, ['actionRequired']);

            foreach ($alerts as $key => $alert) {
                if (!$alert instanceof AlertInterface || $alert->getIsSentReminder()) {
                    unset($alerts[$key]);
                    continue;
                }

                $optimizationIntegration = $alert->getOptimizationIntegration();
                if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                    unset($alerts[$key]);
                    continue;
                }

                if (!$optimizationIntegration->isReminder()) {
                    unset($alerts[$key]);
                    continue;
                }

                // check the time which actionRequire alert was created to now
                // it must be more than 6 hour
                if (!$this->isOutOfDate($alert->getCreatedDate())) {
                    unset($alerts[$key]);
                    continue;
                }

                if (!($optimizationIntegration->getOptimizationFrequency() == '12 hours' || $optimizationIntegration->getOptimizationFrequency() == '24 hours')
                    || !$optimizationIntegration->getOptimizationAlerts() == OptimizationIntegrationInterface::ALERT_NOTIFY_ME_BEFORE_MAKING_OPTIMIZATION
                    || $optimizationIntegration->getActive() == OptimizationIntegrationInterface::ACTIVE_APPLY ) {
                    unset($alerts[$key]);
                }
            }

            if (!is_array($alerts)) {
                continue;
            }

            foreach ($emails as $email) {
                if (!is_array($email) || !array_key_exists('email', $email)) {
                    continue;
                }

                $sentEmailsNumber = $this->sendEmailAlert($output, $publisher, $alerts, $email['email']);
                if ($sentEmailsNumber > 0) {
                    $emailRemindersCount++;
                }
            }

            $output->writeln(sprintf('Sent %d emails to Publisher %s (id:%d).', $emailRemindersCount, $this->getPublisherName($publisher), $publisher->getId()));

            if ($emailRemindersCount >= 1) {
                $sendEmailToPubCount++;
                $emailRemindersCount = 0;
            }
        }

        return $sendEmailToPubCount;
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
        // src/UR/Bundle/AppBundle/Resources/views/Alert/reminder.html.twig
        $sender = $this->getContainer()->getParameter('mailer_sender');
        $emailSubject = '[Pubvantage] Email reminder for Required alert on Pubvantage System';
        $publisherName = $this->getPublisherName($publisher);
        $alertSummaries = $this->getAlertSummaries($alerts);

        $newEmailMessage = (new \Swift_Message())
            ->setFrom($sender)
            ->setTo($email)
            ->setSubject($emailSubject)
            ->setBody(
                $this->getContainer()->get('templating')->render(
                    'URAppBundle:Alert:reminder.html.twig',
                    ['publisher_name' => $publisherName, 'alert_summaries' => $alertSummaries]
                ),
                'text/html');

        // send email
        $sendSuccessNumber = $this->swiftMailer->send($newEmailMessage);

        // update isSent to true for all alerts have been sent email
        if ($sendSuccessNumber > 0) {
            foreach ($alerts as $alert) {
                if (!$alert instanceof AlertInterface) {
                    continue;
                }

                $alert->setIsSentReminder(true);
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
     * get Alert Summaries
     *
     * @param array $alerts
     * @return array
     */
    private function getAlertSummaries(array $alerts)
    {
        $alertSummaries = [];

        foreach ($alerts as $alert) {
            if (!$alert instanceof AlertInterface) {
                continue;
            }

            $alertDetail = $alert->getDetail();
            if (!is_array($alertDetail) || !array_key_exists('message', $alertDetail)) {
                continue;
            }

            $alertSummaries[] = sprintf('[Required Alert ID:%s] %s', $alert->getId(), $alertDetail['message']);
        }

        return array_unique($alertSummaries);
    }

    /**
     * @param DateTime $createdDate
     * @return bool
     */
    private function isOutOfDate(DateTime $createdDate)
    {
        if (!$createdDate instanceof DateTime) {
            return true;
        }

        $now = new DateTime('now', new \DateTimeZone('UTC'));
        $diff = $now->diff($createdDate);
        $second = ((int)$diff->format('%a') * 86400) + ((int)$diff->format('%h') * 60 * 60) + (int)$diff->format('%i') * 60 + (int)$diff->format('%s') ;

        if ($hour = $second / 3600 >= 6) {
            return true;
        }

        return false;
    }
}