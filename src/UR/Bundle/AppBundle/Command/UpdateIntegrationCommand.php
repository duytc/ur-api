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
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\Integration;
use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\StringUtilTrait;

class UpdateIntegrationCommand extends ContainerAwareCommand
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
            ->setName('ur:integration:update')
            ->addArgument('cname', InputOption::VALUE_REQUIRED, 'Integration canonical name')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Integration name')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL,
                'Integration parameters (optional) as name:type, allow multiple parameters separated by comma. 
                Supported types are: plainText (default), date (Y-m-d), dynamicDateRange (last 1,2,3... days) 
                , secure (will be encrypted in database and not show value in ui), option (array of string, separated by ; such as string11; string1 2; String 1 3)
                or multiOptions (multi select options),
                regex (for regex matching) and bool (true | false)
                e.g. -p "username,password:secure,startDate:date,pattern:regex,reportType:option,dailyBreakdown:bool,dimensions:multiOptions"')
            ->addOption('all-publishers', 'a', InputOption::VALUE_NONE,
                'Enable for all users (optional)')
            ->addOption('enables-users', 'u', InputOption::VALUE_OPTIONAL,
                'Enable for special users (optional), allow multiple userId separated by comma, e.g. -u "5,10,3"')
            ->addOption('force-update-all-datasources', 'f', InputOption::VALUE_NONE,
                'Update params to existing datasources-integrations (optional).')
            ->setDescription('Update individual fields of an integration such as display name, params or enabled-users. Notice: require integration cname to identify which integration is being updated.');
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

        $this->logger->info('Starting command...');

        /* get inputs */
        $cName = $input->getArgument('cname');
        $name = $input->getArgument('name');
        $paramsString = $input->getOption('parameters');
        $userIdsString = $input->getOption('enables-users');
        $isAllPublishers = $input->getOption('all-publishers');
        $isUpdateAllDataSourceIntegrations = $input->getOption('force-update-all-datasources');

        if (!$this->validateInput($cName, $output)) {
            return 0;
        }

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        $integration = $integrationManager->findByCanonicalName($cName);

        if (!$integration instanceof IntegrationInterface) {
            $output->writeln(sprintf('Could not found integration with cname %s. Please try other command ur:integration:create to create.', $cName));
            return 0;
        }

        /* Update display name */
        if (!empty($name)) {
            $this->updateDisplayName($integration, $name);
        }

        /* Update parameter */
        if (null !== $paramsString) {
            // validate params
            $paramsString = $input->getOption('parameters');
            if (!empty($paramsString) && !preg_match('/^[a-zA-Z0-9,:;\s-_\/]+$/', $paramsString)) {
                $output->writeln(sprintf('Failed to execute command: parameter must not be null or empty, or wrong format (a-zA-Z0-9,:; -_/)'));
                return 0;
            }

            // make sure only one ":" for each param of params
            $params = explode(',', $paramsString);
            foreach ($params as $param) {
                $paramNameAndType = explode(':', $param);

                if (count($paramNameAndType) > 3) {
                    $output->writeln(sprintf('Failed to execute command: invalid param name and type, please use format "name:type:value".'));
                    return false;
                }
                
                $paramType = count($paramNameAndType) < 2 ? Integration::PARAM_TYPE_PLAIN_TEXT : $paramNameAndType[1];
                if (!in_array($paramType, Integration::$SUPPORTED_PARAM_TYPES)) {
                    $output->writeln(sprintf('Failed to execute command: do not support param type %s.', $paramType));
                    return 0;
                }
            }

            $this->updateParameters($integration, $paramsString);

            // also update param settings for all dataSourceIntegrations if required
            if ($isUpdateAllDataSourceIntegrations) {
                $this->updateDataSourceIntegrations($integration, $paramsString);
            }
        }

        /* Update enable-users */
        if ($userIdsString && $isAllPublishers) {
            $output->writeln(sprintf('Failed to execute command: Do not allow use both option -u and -a.'));
            return 0;
        }

        if ($userIdsString || $isAllPublishers) {
            /** @var PublisherManagerInterface $publisherManager */
            $publisherManager = $container->get('ur_user.domain_manager.publisher');
            $this->updatePublishers($integration, $isAllPublishers, $userIdsString, $publisherManager);
        }

        /* finally, save changes for Integration */
        $integrationManager->save($integration);

        $output->writeln(sprintf('Command runs successfully: %s updated.', 1));

        return 1;
    }

    /**
     * @param string $cname
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput($cname, OutputInterface $output)
    {
        if (empty($cname) || !preg_match('/^[a-z0-9\-_]+$/', $cname)) {
            $output->writeln(sprintf('Failed to execute command: cname must not be null or empty, or wrong format (a-z\-_0-9)'));
            return false;
        }

        return true;
    }

    /**
     * @param IntegrationInterface $integration
     * @param string $name
     */
    private function updateDisplayName(IntegrationInterface $integration, $name)
    {
        $integration->setName($name);
    }

    /**
     * @param IntegrationInterface $integration
     * @param string $paramsString
     */
    private function updateParameters(IntegrationInterface $integration, $paramsString)
    {
        /* parse params from paramsString */
        $params = $this->parseParams($paramsString);

        $integration->setParams($params);
    }

    /**
     * @param IntegrationInterface $integration
     * @param string $paramsString
     */
    private function updateDataSourceIntegrations(IntegrationInterface $integration, $paramsString)
    {
        /* parse params from paramsString */
        $newIntegrationParams = $this->parseParams($paramsString);
        if (!is_array($newIntegrationParams)) {
            return;
        }

        $container = $this->getContainer();
        $dataSourceIntegrationManger = $container->get('ur.domain_manager.data_source_integration');
        $dataSourceIntegrations = $dataSourceIntegrationManger->findByIntegrationCanonicalName($integration->getCanonicalName());

        foreach ($dataSourceIntegrations as $dataSourceIntegration) {
            /** @var DataSourceIntegrationInterface $dataSourceIntegration */
            $oldDataSourceIntegrationParams = $dataSourceIntegration->getOriginalParams();
            if (!is_array($oldDataSourceIntegrationParams)) {
                continue;
            }

            // init new data source integration params values due to new Integration params
            $newDataSourceIntegrationParams = array_map(function ($newIntegrationParam) {
                $newIntegrationParam[Integration::PARAM_KEY_VALUE] = null;
                return $newIntegrationParam;
            }, $newIntegrationParams);

            // bind old value to new data source integration params
            foreach ($oldDataSourceIntegrationParams as $oldDataSourceIntegrationParam) {
                foreach ($newDataSourceIntegrationParams as &$newDataSourceIntegrationParam) {
                    // bind old value to new data source integration params if key and type are not changed
                    // else, the new value is initialized value as above
                    if ($oldDataSourceIntegrationParam[Integration::PARAM_KEY_KEY] == $newDataSourceIntegrationParam[Integration::PARAM_KEY_KEY]) {
                        if ($newDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE] == $oldDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE]) {

                            /** important: if new type == old type and secure type -> set "null" known as no change */
                            if ($oldDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE] == Integration::PARAM_TYPE_SECURE) {
                                $newDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE] = null;
                            } else {
                                $newDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE] = $oldDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE];
                            }
                        }
                        // advance to keep old value if type change from secure to plainText
                        // if from plainText to secure, we already have a listener to do this
                        elseif ($oldDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE] == Integration::PARAM_TYPE_SECURE
                            && $newDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE] == Integration::PARAM_TYPE_PLAIN_TEXT
                        ) {
                            $newDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE] = $dataSourceIntegration->decryptSecureParam($oldDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE]);
                        }
                        // advance to keep old value if type change from secure to plainText
                        // if from plainText to secure, we already have a listener to do this
                        elseif ($oldDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE] == Integration::PARAM_TYPE_PLAIN_TEXT
                            && $newDataSourceIntegrationParam[Integration::PARAM_KEY_TYPE] == Integration::PARAM_TYPE_SECURE
                        ) {
                            $newDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE] = $oldDataSourceIntegrationParam[Integration::PARAM_KEY_VALUE];
                        }
                    }
                }
            }

            $dataSourceIntegration->setParams($newDataSourceIntegrationParams);
            $dataSourceIntegrationManger->save($dataSourceIntegration);
        }
    }

    /**
     * @param IntegrationInterface $integration
     * @param bool $isAllPublisherOption
     * @param string $userIdsStringOption
     * @param PublisherManagerInterface $publisherManager
     */
    private function updatePublishers(IntegrationInterface $integration, $isAllPublisherOption, $userIdsStringOption, PublisherManagerInterface $publisherManager)
    {
        /* update enableForAllUsers for integration */
        $integration->setEnableForAllUsers($isAllPublisherOption);

        /* build integrationPublishers for integration */
        if (!$isAllPublisherOption) {
            $updatePublisherIds = null;
            /** @var PublisherInterface[] $allActivePublishers */
            $allActivePublishers = $publisherManager->all();

            $userIdsStringOption = explode(',', $userIdsStringOption);

            $updatePublisherIds = array_map(function ($userId) {
                return trim($userId);
            }, $userIdsStringOption);

            /* organize old integrationPublishers */
            $currentIntegrationPublishers = $integration->getIntegrationPublishers();
            foreach ($currentIntegrationPublishers as $pos => $integrationPublisher) {
                $pubId = $integrationPublisher->getPublisher()->getId();

                if (!in_array($pubId, $updatePublisherIds)) {
                    unset($currentIntegrationPublishers[$pos]);
                } else {
                    $posDelete = array_search($pubId, $updatePublisherIds);
                    unset($updatePublisherIds[$posDelete]);
                }
            }

            /* add new integrationPublisher for new publishers */
            foreach ($updatePublisherIds as $publisherId) {
                $publisherFilter = array_filter($allActivePublishers, function ($publisher) use ($publisherId) {
                    /** @var PublisherInterface $publisher */
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

            $paramElement = [
                Integration::PARAM_KEY_KEY => $paramNameAndType[0],
                Integration::PARAM_KEY_TYPE => count($paramNameAndType) < 2 ? Integration::PARAM_TYPE_PLAIN_TEXT : $paramNameAndType[1],
            ];

            if (count($paramNameAndType) > 2) {
                //For option params
                if ($paramNameAndType[1] == Integration::PARAM_TYPE_OPTION) {
                    $paramElement[Integration::PARAM_KEY_OPTION_VALUES] = explode(';', $paramNameAndType[2]);
                }

                if ($paramNameAndType[1] == Integration::PARAM_TYPE_MULTI_OPTIONS) {
                    $paramElement[Integration::PARAM_KEY_OPTION_VALUES] = explode(';', $paramNameAndType[2]);
                }

                //For bool params
                if ($paramNameAndType[1] == Integration::PARAM_TYPE_BOOL) {
                    $defaultValue = strtolower($paramNameAndType[2]) == 'true' || strtolower($paramNameAndType[2]) == '1' ? true : false;
                    $paramElement[Integration::PARAM_KEY_DEFAULT_VALUE] = $defaultValue;
                }
            }

            return $paramElement;
        }, $params);

        return $params;
    }
}
