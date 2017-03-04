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
use UR\Entity\Core\Integration;
use UR\Entity\Core\IntegrationPublisher;
use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\StringUtilTrait;

class CreateIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:integration:create')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Integration name')
            ->addArgument('cname', InputOption::VALUE_REQUIRED, 'Integration canonical name')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL,
                'Integration parameters (optional), allow multiple parameters separated by comma, e.g. -p "username,password"')
            ->addOption('all-publishers', 'a', InputOption::VALUE_NONE,
                'Enable for all users')
            ->addOption('enables-users', 'u', InputOption::VALUE_OPTIONAL,
                'Enable users (optional), allow multiple userId separated by comma, e.g. -u "5, 10, 3"')
            ->setDescription('Create or update Integration with name and parameters');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /* get inputs */
        $name = $input->getArgument('name');
        $cName = $input->getArgument('cname');
        $paramsString = $input->getOption('parameters');
        $userIdsStringOption = $input->getOption('enables-users');
        $isAllPublisherOption = $input->getOption('all-publishers');

        if (!$this->validateInput($name, $cName, $paramsString, $userIdsStringOption, $isAllPublisherOption)) {
            return;
        }

        // parse params from paramsString
        $params = null;
        if (!empty($paramsString)) {
            $params = explode(',', $paramsString);
            // trim
            $params = array_map(function ($param) {
                return trim($param);
            }, $params);
        }

        /** @var PublisherManagerInterface $publisherManager */
        $publisherManager = $container->get('ur_user.domain_manager.publisher');
        /**
         *  Get list user ids from command
         */
        $updatePublisherIds = null;
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

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        $integration = $integrationManager->findByCanonicalName($cName);
        $isFoundIntegration = ($integration instanceof IntegrationInterface);

        $currentIntegrationPublishers = null;
        if (!$isFoundIntegration) {
            $integration = new Integration();
            $currentIntegrationPublishers = [];
        } else {
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

        $integration->setName($name);
        $integration->setParams($params);
        $integration->setCanonicalName($cName);
        $integration->setIntegrationPublishers($currentIntegrationPublishers);
        $integrationManager->save($integration);

        $output->writeln(sprintf('command run successfully: %d %s.', 1, ($isFoundIntegration ? 'updated' : 'created')));
    }

    /**
     * @param string $name
     * @param string $cname
     * @param string $paramsString
     * @param string $userIdsStringOption
     * @param string $isAllPublisherOption
     * @return bool
     */
    private function validateInput($name, $cname, $paramsString, $userIdsStringOption, $isAllPublisherOption)
    {
        if ($name == null || empty($name)) {
            $this->logger->info(sprintf('command run failed: name must not be null or empty'));
            return false;
        }

        if ($cname == null || empty($cname) || !preg_match('/^[a-z-_]/', $cname)) {
            $this->logger->info(sprintf('command run failed: cname must not be null or empty, or wrong format (a-z-_)'));
            return false;
        }

        if (!$userIdsStringOption && !$isAllPublisherOption){
            $this->logger->info(sprintf('command run failed: missing publishers info, use option -u or -a'));
            return false;
        }

        if ($userIdsStringOption && $isAllPublisherOption){
            $this->logger->info(sprintf('command run failed: not allow use both option -u and -a'));
            return false;
        }
        return true;
    }
}