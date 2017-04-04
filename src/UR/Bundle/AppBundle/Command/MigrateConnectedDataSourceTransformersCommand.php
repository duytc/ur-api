<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;

class MigrateConnectedDataSourceTransformersCommand extends ContainerAwareCommand
{
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
            ->setName('ur:migrate:connected-data-source:transformers')
            ->setDescription('Migrate Connected data source transformers to latest format');
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
        $connectedDataSources = $this->connectedDataSourceManager->all();

        $output->writeln(sprintf('updating %d connected data source transformers to latest format', count($connectedDataSources)));

        // migrate connected data source transformers
        $migratedAlertsCount = $this->migrateConnectedDataSourceTransformers($output, $connectedDataSources);

        $output->writeln(sprintf('command run successfully: %d connected data sources updated.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateConnectedDataSourceTransformers(OutputInterface $output, array $connectedDataSources)
    {
        $migratedCount = 0;

        foreach ($connectedDataSources as $connectedDataSource) {
            // sure is ConnectedDataSourceInterface
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            /*
             * from old format:
             * [
             *     ...
             *     {
             *        "fields":[
             *           ...
             *           {
             *              "field":"calc_field",
             *              "value":null,
             *              "expression":"[in-view measurable impressions]   *   1000",
             *              // CHANGE HERE
             *           },
             *           ...
             *        ],
             *        "openStatus":true,
             *        "type":"addCalculatedField",
             *        "field":null
             *     },
             *     ...
             *  ]
             */
            $oldTransformers = $connectedDataSource->getTransforms();
            if (!is_array($oldTransformers)) {
                continue;
            }

            /*
             * migrate to new format
             * [
             *     ...
             *     {
             *        "fields":[
             *           ...
             *           {
             *              "field":"calc_field",
             *              "value":null,
             *              "expression":"[in-view measurable impressions]   *   1000",
             *              "defaultValues": [] // CHANGE HERE
             *           },
             *           ...
             *        ],
             *        "openStatus":true,
             *        "type":"addCalculatedField",
             *        "field":null
             *     },
             *     ...
             *  ]
             */
            $hasChanged = false;

            foreach ($oldTransformers as &$oldTransformer) {
                if (!array_key_exists(CollectionTransformerInterface::FIELDS_KEY, $oldTransformer)
                    || !array_key_exists(CollectionTransformerInterface::TYPE_KEY, $oldTransformer)
                    || $oldTransformer[CollectionTransformerInterface::TYPE_KEY] !== CollectionTransformerInterface::ADD_CALCULATED_FIELD
                ) {
                    continue;
                }

                $oldCalculatedFieldTransformers = $oldTransformer[CollectionTransformerInterface::FIELDS_KEY];
                if (!is_array($oldCalculatedFieldTransformers)) {
                    continue;
                }

                foreach ($oldCalculatedFieldTransformers as &$oldCalculatedFieldTransformer) {
                    // add/modify element defaultValues with default value is empty array
                    if (!array_key_exists(AddCalculatedField::DEFAULT_VALUES_KEY, $oldCalculatedFieldTransformer)
                        || !is_array($oldCalculatedFieldTransformer[AddCalculatedField::DEFAULT_VALUES_KEY])
                    ) {
                        $oldCalculatedFieldTransformer[AddCalculatedField::DEFAULT_VALUES_KEY] = [];
                        $hasChanged = true;
                    }
                }

                // important: set again for oldTransformer
                $oldTransformer[CollectionTransformerInterface::FIELDS_KEY] = $oldCalculatedFieldTransformers;
            }

            if ($hasChanged) {
                $migratedCount++;
                $connectedDataSource->setTransforms($oldTransformers);
                $this->connectedDataSourceManager->save($connectedDataSource);
            }
        }

        return $migratedCount;
    }
}