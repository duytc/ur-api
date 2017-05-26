<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Behaviors\CreateUrApiKeyTrait;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class MigrateDataSourceAPIKeyCommand extends ContainerAwareCommand
{
    use CreateUrApiKeyTrait;

    /** @var  Logger */
    private $logger;

    /** @var  DataSourceManagerInterface */
    private $dataSourceManager;

    /** @var  EntityManager */
    private $em;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:data-source:generate-api-key')
            ->setDescription('Migrate new api key format for data source');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');
        $this->em = $container->get('doctrine.orm.entity_manager');

        //get all data sources
        $dataSources = $this->dataSourceManager->all();

        $output->writeln(sprintf('migrating %d data sources with new api key', count($dataSources)));

        $migrateCount = $this->migrateDataSourceAPIKey($output, $dataSources);

        $output->writeln(sprintf('command run successfully: %d data sources', $migrateCount));
    }

    /**
     * update api key
     *
     * @param OutputInterface $output
     * @param array|DataSourceInterface[] $dataSources
     * @return int migrated dataSets count
     */
    private function migrateDataSourceAPIKey(OutputInterface $output, array $dataSources)
    {
        if (!is_array($dataSources)) {
            return 0;
        }

        $migrateCount = 0;
        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $publisher = $dataSource->getPublisher();
            if (!$publisher instanceof PublisherInterface) {
                continue;
            }

            $user = $publisher->getUser();
            if (!$user instanceof UserRoleInterface) {
                continue;
            }

            $message = sprintf('Migrate api key for dataSource %s, id %s', $dataSource->getName(), $dataSource->getId());
            $output->writeln($message);
            $this->logger->info($message);

            $apiKey = $this->generateUrApiKey($user->getUsername());
            $dataSource->setApiKey($apiKey);
            $this->em->persist($dataSource);

            $migrateCount++;
        }
        $this->em->flush();

        return $migrateCount;
    }
}