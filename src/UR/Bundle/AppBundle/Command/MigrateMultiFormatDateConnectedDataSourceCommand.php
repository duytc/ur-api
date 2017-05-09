<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\StringUtilTrait;

class MigrateMultiFormatDateConnectedDataSourceCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:connected-data-source:multi-date-transform')
            ->setDescription('Migrate connected data source multi date transform');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->connectedDataSourceManager = $container->get('ur.domain_manager.connected_data_source');

        //get all connected data source
        $connectedDataSources = $this->connectedDataSourceManager->all();

        $output->writeln(sprintf('migrating %d connected data source date transform', count($connectedDataSources)));

        // migrate connected data source multi date transforms
        $migratedAlertsCount = $this->migrateMultiDateTransform($output, $connectedDataSources);

        $output->writeln(sprintf('command run successfully: %d ConnectedDataSources.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateMultiDateTransform(OutputInterface $output, array $connectedDataSources)
    {
        $migratedCount = 0;

        foreach ($connectedDataSources as $connectedDataSource) {
            /*
             * from format: string  "Y-m-d"
             */
            $transforms = $connectedDataSource->getTransforms();

            /*
             *  migrate to new format:
             * [
             *     [
             *          "format" => "Y-m-d",
             *          "isCustomFormat" => true
             *      ]
             *     ...
             * ]
             */
            $isChange = 0;
            foreach ($transforms as &$transform) {
                if ($transform[ColumnTransformerInterface::TYPE_KEY] !== ColumnTransformerInterface::DATE_FORMAT) {
                    continue;
                }

                if (!array_key_exists(DateFormat::FROM_KEY, $transform)
                ) {
                    continue;
                }

                if (is_array($transform[DateFormat::FROM_KEY])) {
                    continue;
                }

                $transform[DateFormat::FROM_KEY] = [
                    [
                        DateFormat::FORMAT_KEY => $transform[DateFormat::FROM_KEY],
                        DateFormat::IS_CUSTOM_FORMAT_DATE_FROM => array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $transform) ? $transform[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM] : false
                    ]
                ];

                if (array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $transform)) {
                    $transform[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM];
                }

                $isChange++;
            }

            if ($isChange > 0) {
                $migratedCount++;
                $connectedDataSource->setTransforms($transforms);
                $this->connectedDataSourceManager->save($connectedDataSource);
            }
        }

        return $migratedCount;
    }
}