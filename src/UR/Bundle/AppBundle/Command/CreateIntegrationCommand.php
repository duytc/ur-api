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

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:integration:create')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Integration name')
            ->addArgument('cname', InputOption::VALUE_REQUIRED, 'Integration canonical name')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL,
                'Integration parameters (optional) as name:type, allow multiple parameters separated by comma. 
                Supported types are: plainText (default), date (Y-m-d), dynamicDateRange (last 1,2,3... days) 
                and secure (will be encrypted in database and not show value in ui). 
                e.g. -p "username,password:secure,startDate:date"')
            ->addOption('all-publishers', 'a', InputOption::VALUE_NONE,
                'Enable for all users')
            ->addOption('enables-users', 'u', InputOption::VALUE_OPTIONAL,
                'Enable for special users, allow multiple userId separated by comma, e.g. -u "5,10,3"')
            ->setDescription('Create or update Integration with name, canonical name and parameters');
    }

    /**
     * @inheritdoc
     */
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

        if (!$this->validateInput($input, $output)) {
            return 0;
        }

        /* parse params from paramsString */
        $params = $this->parseParams($paramsString);

        /* check if cname existed */
        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        $integration = $integrationManager->findByCanonicalName($cName);
        $isFoundIntegration = ($integration instanceof IntegrationInterface);

        if ($isFoundIntegration) {
            $output->writeln(sprintf('integration with cname %s existed. Please try command ur:integration:update to update', $cName));
            return 0;
        }

        /* create new integration */
        $integration = new Integration();
        $integration->setName($name);
        $integration->setParams($params);
        $integration->setCanonicalName($cName);
        $integration->setEnableForAllUsers($isAllPublisherOption);

        /* build integrationPublishers for integration */
        if (!$isAllPublisherOption) {
            /** @var PublisherManagerInterface $publisherManager */
            $publisherManager = $container->get('ur_user.domain_manager.publisher');
            $updatePublisherIds = null;
            $allActivePublisher = $publisherManager->all();

            $userIdsStringOption = explode(',', $userIdsStringOption);

            $updatePublisherIds = array_map(function ($userId) {
                return trim($userId);
            }, $userIdsStringOption);

            $currentIntegrationPublishers = [];

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
        }

        /* finally, persist new integration */
        $integrationManager->save($integration);

        $output->writeln(sprintf('command run successfully: %d %s.', 1, 'created'));

        return 1;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput(InputInterface $input, OutputInterface $output)
    {
        // validate name
        $name = $input->getArgument('name');
        if (empty($name)) {
            $output->writeln(sprintf('command run failed: name must not be null or empty'));
            return false;
        }

        // validate cname
        $cname = $input->getArgument('cname');
        if (empty($cname) || !preg_match('/^[a-z0-9\-_]+$/', $cname)) {
            $output->writeln(sprintf('command run failed: cname must not be null or empty, or wrong format (a-z\-_)'));
            return false;
        }

        // validate params
        $paramsString = $input->getOption('parameters');
        if (!empty($paramsString) && !preg_match('/^[a-zA-Z0-9,:\-_]+$/', $paramsString)) {
            $output->writeln(sprintf('command run failed: parameter must not be null or empty, or wrong format (a-zA-Z,:\-_)'));
            return false;
        }

        // make sure only one ":" for each param of params
        $params = explode(',', $paramsString);
        foreach ($params as $param) {
            $paramNameAndType = explode(':', $param);
            if (count($paramNameAndType) > 2) {
                $output->writeln(sprintf('command run failed: invalid param name and type, please use format "name:type"'));
                return false;
            }

            $paramType = count($paramNameAndType) < 2 ? Integration::PARAM_TYPE_PLAIN_TEXT : $paramNameAndType[1];
            if (!in_array($paramType, Integration::$SUPPORTED_PARAM_TYPES)) {
                $output->writeln(sprintf('command run failed: not supported param type %s', $paramType));
                return false;
            }
        }

        // validate enables-users and all publishers
        $hasUserIdsStringOption = $input->getOption('enables-users') !== null;
        $isAllPublisherOption = $input->getOption('all-publishers');
        if ((!$hasUserIdsStringOption && !$isAllPublisherOption) || ($hasUserIdsStringOption && $isAllPublisherOption)) {
            $output->writeln(sprintf('command run failed: invalid publishers info, require only one of options -u or -a'));
            return false;
        }

        // validate enables-users
        if ($hasUserIdsStringOption) {
            $userIdsStringOption = $input->getOption('enables-users');

            if (empty($userIdsStringOption)) {
                $output->writeln(sprintf('command run failed: enables-users must not be null or empty'));
                return false;
            }
        }

        return true;
    }

    /**
     * parse Params. Support , as separator between params and : as separator between name and type
     *
     * @param string $paramsString
     * @return array|null return null if paramsString empty|null. Return array if valid, array format as:
     * [
     * [ 'key' => <param name>, 'type' => <param type> ],
     * ...
     * ]
     */
    private function parseParams($paramsString)
    {
        if (empty($paramsString)) {
            return null;
        }

        $params = explode(',', $paramsString);

        $params = array_map(function ($param) {
            // parse name:type
            $paramNameAndType = explode(':', trim($param));

            return [
                'key' => $paramNameAndType[0],
                'type' => count($paramNameAndType) < 2 ? Integration::PARAM_TYPE_PLAIN_TEXT : $paramNameAndType[1]
            ];
        }, $params);

        return $params;
    }
}