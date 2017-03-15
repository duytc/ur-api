<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Entity\Core\IntegrationPublisher;
use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\StringUtilTrait;

class UpdateIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:integration:update')
            ->addArgument('cname', InputOption::VALUE_REQUIRED, 'Integration canonical name')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Integration name')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL,
                'Integration parameters (optional), allow multiple parameters separated by comma, e.g. -p "username,password"')
            ->addOption('all-publishers', 'a', InputOption::VALUE_NONE,
                'Enable for all users')
            ->addOption('enables-users', 'u', InputOption::VALUE_OPTIONAL,
                'Enable users (optional), allow multiple userId separated by comma, e.g. -u "5, 10, 3"')
            ->setDescription('update individual fields of an integration such as display name, params or enabled-users. Notice: require integration cname to identify which integration is being updated.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /* get inputs */
        $cName = $input->getArgument('cname');
        $name = $input->getArgument('name');
        $paramsString = $input->getOption('parameters');
        $userIdsStringOption = $input->getOption('enables-users');
        $isAllPublisherOption = $input->getOption('all-publishers');

        if (!$this->validateInput($cName)) {
            return;
        }

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        $integration = $integrationManager->findByCanonicalName($cName);
        $isFoundIntegration = ($integration instanceof IntegrationInterface);

        if (!$isFoundIntegration) {
            $output->writeln(sprintf('Could not found integration %s. Please try other command ur:integration:create', $cName));
            return;
        }
        /**
         * Update display name
         */
        if ($name != null && !empty($name)) {
            $this->updateDisplayName($integration, $name, $integrationManager, $output);
        }

        /**
         * Update parameter
         */
        // parse params from paramsString
        $params = null;
        if (!empty($paramsString)) {
            $params = explode(',', $paramsString);
            // trim
            $params = array_map(function ($param) {
                return trim($param);
            }, $params);
        }
        if ($params){
            $this->updateParameters($integration, $params, $integrationManager, $output);
        }

        /**
         * Update publishers
         */
        if ($userIdsStringOption && $isAllPublisherOption){
            $this->logger->info(sprintf('command run failed: not allow use both option -u and -a'));
        } elseif ($userIdsStringOption || $isAllPublisherOption) {
            $this->updatePublishers($integration, $isAllPublisherOption, $userIdsStringOption, $container, $integrationManager, $output);
        }
        $output->writeln(sprintf('command run successfully: %s.', 'updated'));
    }

    /**
     * @param string $cname
     * @return bool
     */
    private function validateInput($cname)
    {
        if ($cname == null || empty($cname) || !preg_match('/^[0-9a-z-_]/', $cname)) {
            $this->logger->info(sprintf('command run failed: cname must not be null or empty, or wrong format (a-z-_)'));
            return false;
        }
        return true;
    }

    /**
     * @param IntegrationInterface $integration
     * @param string $name
     * @param IntegrationManagerInterface $integrationManager
     * @param OutputInterface $output
     */
    private function updateDisplayName($integration, $name, $integrationManager, $output){
        if (!$integration instanceof IntegrationInterface){
            return;
        }
        $oldCname = $integration->getCanonicalName();
        $integration->setName($name);
        $integration->setCanonicalName($oldCname);
        $integrationManager->save($integration);
        $output->writeln(sprintf('command run successfully: %s.', 'updated display name'));
    }

    /**
     * @param IntegrationInterface $integration
     * @param string $params
     * @param IntegrationManagerInterface $integrationManager
     * @param OutputInterface $output
     */
    private function updateParameters($integration, $params,  $integrationManager, $output){
        if (!$integration instanceof IntegrationInterface){
            return;
        }
        $integration->setParams($params);
        $integrationManager->save($integration);
        $output->writeln(sprintf('command run successfully: %s.', 'updated parameters'));
    }

    /**
     * @param IntegrationInterface $integration
     * @param bool $isAllPublisherOption
     * @param string $userIdsStringOption
     * @param ContainerInterface $container
     * @param IntegrationManagerInterface $integrationManager
     * @param OutputInterface $output
     */
    private function updatePublishers($integration, $isAllPublisherOption, $userIdsStringOption, $container, $integrationManager, $output){
        if (!$integration instanceof IntegrationInterface){
            return;
        }
        /** @var PublisherManagerInterface $publisherManager */
        $publisherManager = $container->get('ur_user.domain_manager.publisher');
        /**
         *  Get list user ids from command
         */
        $updatePublisherIds = null;if (!$integration instanceof IntegrationInterface){
            return;
        }
        $allActivePublisher = $publisherManager->all();

        if ($isAllPublisherOption) {
            $updatePublisherIds = array_map(function ($publisher) {
                /**@var PublisherInterface $publisher */
                return $publisher->getId();
            }, $allActivePublisher);
        } else {
            $userIdsStringOption = explode(',', $userIdsStringOption);
            $updatePublisherIds = array_map(function ($userId) {
                return trim($userId);
            }, $userIdsStringOption);
        }

        $currentIntegrationPublishers = $integration->getIntegrationPublishers();
        foreach ($currentIntegrationPublishers as $pos => $integrationPublisher){
            $pubId = $integrationPublisher->getPublisher()->getId();
            if (!in_array($pubId, $updatePublisherIds)){
                unset($currentIntegrationPublishers[$pos]);
            } else {
                $posDelete = array_search($pubId, $updatePublisherIds);
                unset($updatePublisherIds[$posDelete]);
            }
        }

        foreach ($updatePublisherIds as $publisherId) {
            $publisherFilter = array_filter($allActivePublisher, function ($publisher) use ($publisherId) {
                /**@var PublisherInterface $publisher */
                return $publisher->getId() == $publisherId;
            });
            $publisher = $publisherFilter ? reset($publisherFilter) : null;

            if ($publisher instanceof PublisherInterface) {
                $integrationPublisher = new IntegrationPublisher();
                $integrationPublisher->setIntegration($integration);
                $integrationPublisher->setPublisher($publisher);
                $currentIntegrationPublishers[] = $integrationPublisher;
            }
        }

        $integration->setIntegrationPublishers($currentIntegrationPublishers);
        $integrationManager->save($integration);
        $output->writeln(sprintf('command run successfully: %s.', 'updated publishers'));
    }
}