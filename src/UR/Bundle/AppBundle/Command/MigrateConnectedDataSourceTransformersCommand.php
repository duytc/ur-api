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
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class MigrateConnectedDataSourceTransformersCommand extends ContainerAwareCommand
{
    const VERSION_MIGRATE_TRANSFORMS_CALCULATED_FIELD_WITH_DEFAULT_VALUE = 1;
    const VERSION_MIGRATE_TRANSFORMS_DATE_FORMAT_FULL_TEXT = 2;
    const VERSION_MIGRATE_TRANSFORMS_DATE_FORMAT_SMART_TIMEZONE = 3;

    static $CURRENT_VERSION = self::VERSION_MIGRATE_TRANSFORMS_DATE_FORMAT_SMART_TIMEZONE;

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
            ->setDescription('Migrate Connected data source transformers to latest format.');
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

        $output->writeln(sprintf('Updating %d connected data source transformers to latest format.', count($connectedDataSources)));

        $migratedAlertsCount = 0;

        switch (self::$CURRENT_VERSION) {
            case self::VERSION_MIGRATE_TRANSFORMS_CALCULATED_FIELD_WITH_DEFAULT_VALUE:
                // migrate connected data source transformers to support calculated field transform with default value
                $migratedAlertsCount = $this->migrateTransformersCalculatedFieldWithDefaultValue($output, $connectedDataSources);

                break;

            case self::VERSION_MIGRATE_TRANSFORMS_DATE_FORMAT_FULL_TEXT:
                // migrate connected data source transformers to support datetime transform with full text
                $migratedAlertsCount = $this->migrateTransformersDateTimeFullText($output, $connectedDataSources);

                break;

            case self::VERSION_MIGRATE_TRANSFORMS_DATE_FORMAT_SMART_TIMEZONE:
                // migrate connected data source transformers to support datetime transform with smart timezone
                $migratedAlertsCount = $this->migrateTransformersDateTimeSmartTimeZone($output, $connectedDataSources);

                break;
        }

        $output->writeln(sprintf('The command runs successfully: %d connected data sources updated.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateTransformersCalculatedFieldWithDefaultValue(OutputInterface $output, array $connectedDataSources)
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

    /**
     * migrate Transformers DateTime with Full Text
     *
     * @param OutputInterface $output
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateTransformersDateTimeFullText(OutputInterface $output, array $connectedDataSources)
    {
        $migratedCount = 0;

        $supportedDateFormat = DateFormat::SUPPORTED_DATE_FORMATS;
        $supportedDateFormatFlipped = array_flip($supportedDateFormat);

        foreach ($connectedDataSources as $connectedDataSource) {
            // sure is ConnectedDataSourceInterface
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            /*
             * from old format: Y-m-d, Y/m/d, Y/m/d H:i:s, ...
             */
            $oldTransformers = $connectedDataSource->getTransforms();
            if (!is_array($oldTransformers)) {
                continue;
            }

            /*
             * migrate to new format: YYYY-MM-DD, YYYY/MM/DD, YYYY/MM/DD HH:mm:ss, ...
             */
            $hasChanged = false;

            foreach ($oldTransformers as &$oldTransformer) {
                if (!array_key_exists(ColumnTransformerInterface::FIELD_KEY, $oldTransformer)
                    || !array_key_exists(ColumnTransformerInterface::TYPE_KEY, $oldTransformer)
                    || $oldTransformer[ColumnTransformerInterface::TYPE_KEY] !== ColumnTransformerInterface::DATE_FORMAT
                    || !array_key_exists(DateFormat::FROM_KEY, $oldTransformer)
                    || !array_key_exists(DateFormat::TO_KEY, $oldTransformer)
                ) {
                    continue; // only for date format
                }

                /*
                 * {
                 *    "field":"timestamp_hour",
                 *    "type":"date",
                 *    "to":"Y-m-d H:i:s",
                 *    "openStatus":true,
                 *    "from":[
                 *       {
                 *          "isCustomFormatDateFrom":false,
                 *          "format":"Y-m-d H:i:s P"
                 *       }
                 *    ],
                 *    "timezone":"UTC"
                 * },
                 */

                // from date format
                $fromDateFormatConfigs = $oldTransformer[DateFormat::FROM_KEY];
                if (!is_array($fromDateFormatConfigs)) {
                    continue;
                }

                foreach ($fromDateFormatConfigs as &$fromDateFormatConfig) {
                    if (!array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $fromDateFormatConfig)
                        || !array_key_exists(DateFormat::FORMAT_KEY, $fromDateFormatConfig)
                    ) {
                        continue; // only for date format
                    }

                    $hasChanged = true;

                    $isCustomFormatDateFrom = $fromDateFormatConfig[DateFormat::IS_CUSTOM_FORMAT_DATE_FROM];
                    $fromDateFormat = $fromDateFormatConfig[DateFormat::FORMAT_KEY];

                    if ($isCustomFormatDateFrom) {
                        // important: keep replacing MMMM before MMM, MMM before MM, MM before M and so on...
                        //// Y => YYYY
                        if (strpos($fromDateFormat, 'YY') === false) { // need check if YY (also YYYY) existing
                            $fromDateFormat = str_replace('Y', 'YYYY', $fromDateFormat); // 4 digits
                        }
                        //// y => YY
                        $fromDateFormat = str_replace('y', 'YY', $fromDateFormat); // 2 digits

                        //// F => MMMM
                        $fromDateFormat = str_replace('F', 'MMMM', $fromDateFormat); // full name
                        //// M => MMM
                        if (strpos($fromDateFormat, 'MM') === false) { // need check if MM (also MMM, MMMM) existing
                            $fromDateFormat = str_replace('M', 'MMM', $fromDateFormat); // 3 characters
                        }
                        //// m => MM
                        $fromDateFormat = str_replace('mm', '$$MM$$', $fromDateFormat); // temporarily
                        $fromDateFormat = str_replace('m', 'MM', $fromDateFormat); // 2 characters
                        $fromDateFormat = str_replace('$$MM$$', 'mm', $fromDateFormat); // temporarily
                        //// n => M
                        $fromDateFormat = str_replace('n', 'M', $fromDateFormat); // 1 character without leading zeros

                        //// d => DD
                        $fromDateFormat = str_replace('d', 'DD', $fromDateFormat); // 2 characters
                        //// j => D
                        $fromDateFormat = str_replace('j', 'D', $fromDateFormat); // 1 character without leading zeros

                        // replacing HH:mm:ss to H:i:s
                        //// H => HH
                        if (strpos($fromDateFormat, 'HH') === false) { // need check if HH existing
                            $fromDateFormat = str_replace('H', 'HH', $fromDateFormat); // hour
                        }
                        //// i => mm
                        $fromDateFormat = str_replace('i', 'mm', $fromDateFormat); // min
                        //// s => ss
                        if (strpos($fromDateFormat, 'ss') === false) { // need check if ss existing
                            $fromDateFormat = str_replace('s', 'ss', $fromDateFormat); // sec
                        }
                    } else {
                        if (array_key_exists($fromDateFormat, $supportedDateFormatFlipped)) {
                            $fromDateFormat = $supportedDateFormatFlipped[$fromDateFormat];
                        }
                    }

                    $fromDateFormatConfig[DateFormat::FORMAT_KEY] = $fromDateFormat;
                }

                // to date format
                $toDateFormat = $oldTransformer[DateFormat::TO_KEY];
                if (array_key_exists($toDateFormat, $supportedDateFormatFlipped)) {
                    $toDateFormat = $supportedDateFormatFlipped[$toDateFormat];
                }

                // important: set again for oldTransformer
                $oldTransformer[DateFormat::FROM_KEY] = $fromDateFormatConfigs;
                $oldTransformer[DateFormat::TO_KEY] = $toDateFormat;
            }

            if ($hasChanged) {
                $migratedCount++;
                $connectedDataSource->setTransforms($oldTransformers);
                $this->connectedDataSourceManager->save($connectedDataSource);
            }
        }

        return $migratedCount;
    }

    /**
     * migrate Transformers DateTime Smart TimeZone
     * use T for all text
     *
     * @param OutputInterface $output
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateTransformersDateTimeSmartTimeZone(OutputInterface $output, array $connectedDataSources)
    {
        $migratedCount = 0;

        foreach ($connectedDataSources as $connectedDataSource) {
            // sure is ConnectedDataSourceInterface
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $oldTransformers = $connectedDataSource->getTransforms();
            if (!is_array($oldTransformers)) {
                continue;
            }

            $hasChanged = false;

            foreach ($oldTransformers as &$oldTransformer) {
                if (!array_key_exists(ColumnTransformerInterface::FIELD_KEY, $oldTransformer)
                    || !array_key_exists(ColumnTransformerInterface::TYPE_KEY, $oldTransformer)
                    || $oldTransformer[ColumnTransformerInterface::TYPE_KEY] !== ColumnTransformerInterface::DATE_FORMAT
                    || !array_key_exists(DateFormat::FROM_KEY, $oldTransformer)
                    || !array_key_exists(DateFormat::TO_KEY, $oldTransformer)
                ) {
                    continue; // only for date format
                }

                /*
                 * {
                 *    "field":"timestamp_hour",
                 *    "type":"date",
                 *    "to":"Y-m-d H:i:s",
                 *    "openStatus":true,
                 *    "from":[
                 *       {
                 *          "isCustomFormatDateFrom":false,
                 *          "format":"Y-m-d H:i:s T"
                 *       }
                 *    ],
                 *    "timezone":"UTC"
                 * },
                 */

                // from date format
                $fromDateFormatConfigs = $oldTransformer[DateFormat::FROM_KEY];
                if (!is_array($fromDateFormatConfigs)) {
                    continue;
                }

                foreach ($fromDateFormatConfigs as &$fromDateFormatConfig) {
                    if (!array_key_exists(DateFormat::IS_CUSTOM_FORMAT_DATE_FROM, $fromDateFormatConfig)
                        || !array_key_exists(DateFormat::FORMAT_KEY, $fromDateFormatConfig)
                    ) {
                        continue; // only for date format
                    }

                    $fromDateFormat = $fromDateFormatConfig[DateFormat::FORMAT_KEY];
                    // Check if $fromDateFormat contains e, O or P, those will be replace with T character
                    if (false !== strpos($fromDateFormat, 'e') || false !== strpos($fromDateFormat, 'O') || false !== strpos($fromDateFormat, 'P')) {
                        $fromDateFormat = str_replace('e', 'T', $fromDateFormat);
                        $fromDateFormat = str_replace('O', 'T', $fromDateFormat);
                        $fromDateFormat = str_replace('P', 'T', $fromDateFormat);

                        $hasChanged = true;
                    }

                    $fromDateFormatConfig[DateFormat::FORMAT_KEY] = $fromDateFormat;
                }

                // important: set the changes for Transformer
                $oldTransformer[DateFormat::FROM_KEY] = $fromDateFormatConfigs;
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