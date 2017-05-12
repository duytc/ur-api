<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Metadata\Email\EmailMetadata;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Filter\FilterFactory;
use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\ConvertCase;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\NormalizeText;
use UR\Service\Parser\Transformer\Collection\ReplaceText;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;
use UR\Service\Parser\Transformer\TransformerFactory;

class MigrateConnectedDataSourceFieldPrefixCommand extends ContainerAwareCommand
{
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;

    private $oldMapFields;

    private $oldTempFields;

    /**
     * @var ConnectedDataSourceInterface
     */
    private $connectedDataSource;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:connected-data-source:field-prefix')
            ->setDescription('Migrate Connected data source Add prefix $$FILE$$ to fields from file and $$TEMP$$ to fields from temp');
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
        $migratedAlertsCount = $this->migrateConnectedDataSourcePrefix($output, $connectedDataSources);

        $output->writeln(sprintf('command run successfully: %d connected data sources updated.', $migratedAlertsCount));
    }

    /**
     * migrate Integrations to latest format
     *
     * @param OutputInterface $output
     * @param array|ConnectedDataSourceInterface[] $connectedDataSources
     * @return int migrated integrations count
     */
    private function migrateConnectedDataSourcePrefix(OutputInterface $output, array $connectedDataSources)
    {
        $migratedCount = 0;

        foreach ($connectedDataSources as $connectedDataSource) {
            // sure is ConnectedDataSourceInterface
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $this->connectedDataSource = $connectedDataSource;

            /*
             * from old format:
             *
             *
             * "mapFields":{
                  "$$FILE$$time":"date",
                  "$$FILE$$impressions":"impressions"
               }
             *
             *
             * filter: [{"field":"supply partner","type":"text","compareValue":null,"comparison":"isNotEmpty"}]
             *
             * transform: [
                   {
                      "fields":[

                      ],
                      "openStatus":true,
                      "type":"augmentation",
                      "field":null,
                      "mapCondition":{
                         "leftSide":"demand name",
                         "rightSide":"demand_tag_name"
                      },
                      "mapFields":[
                         {
                            "leftSide":"texxt",
                            "rightSide":"client_id"
                         }
                      ],
                      "customCondition":[

                      ],
                      "mapDataSet":13
                   },
                   {
                      "fields":[

                      ],
                      "openStatus":true,
                      "type":"subsetGroup",
                      "groupFields":[
                         "complete"
                      ],
                      "field":null,
                      "mapFields":[
                         {
                            "leftSide":"ad_requests",
                            "rightSide":"demand url"
                         }
                      ]
                   }
                ]
             */

            $this->oldTempFields = $connectedDataSource->getTemporaryFields();
            if (!is_array($this->oldTempFields)) {
                $this->oldTempFields = [];
            }

            $this->oldMapFields = $connectedDataSource->getMapFields();
            if (!is_array($this->oldMapFields)) {
                $this->oldMapFields = [];
            }

            $oldFilters = $connectedDataSource->getFilters();
            if (!is_array($oldFilters)) {
                $oldFilters = [];
            }

            $oldTransformers = $connectedDataSource->getTransforms();
            if (!is_array($oldTransformers)) {
                $oldTransformers = [];
            }

            /*
             * "mapFields":{
                  "$$FILE$$time":"date",
                  "$$FILE$$impressions":"impressions"
               }

                filter: [{"field":"$$FILE$$supply partner","type":"text","compareValue":null,"comparison":"isNotEmpty"}]

             transform: [
                           {
                              "type":"augmentation",
                              "mapCondition":{
                                 "leftSide":"$$FILE$$demand name",
                                 "rightSide":"demand_tag_name"
                              },
                              "mapFields":[
                                 {
                                    "leftSide":"texxt",
                                    "rightSide":"client_id"
                                 }
                              ],
                              "customCondition":[

                              ],
                              "mapDataSet":13
                           },
                           {
                              "type":"subsetGroup",
                              "groupFields":[
                                 "$$FILE$$complete"
                              ],
                              "mapFields":[
                                 {
                                    "leftSide":"ad_requests",
                                    "rightSide":"$$FILE$$demand url"
                                 }
                              ]
                           },
                           {
                              "fields":[
                                 {
                                    "names":[
                                       "$$FILE$$ad responses"
                                    ],
                                    "direction":"asc"
                                 },
                                 {
                                    "names":[
                                       "$$FILE$$ad loads"
                                    ],
                                    "direction":"desc"
                                 }
                              ],
                              "type":"sortBy"
                           },
                           {
                              "fields":[
                                 "$$FILE$$demand id",
                                 "$$FILE$$ad loads%"
                              ],
                              "type":"groupBy"
                           },
                           {
                              "fields":[
                                 {
                                    "field":"type",
                                    "value":"[$$FILE$$demand name]+[$$FILE$$ad loads]*100",
                                    "expression":null
                                 }
                              ],
                              "type":"addField"
                           },
                           {
                              "fields":[
                                 {
                                    "field":"total_impresion",
                                    "value":null,
                                    "defaultValues":[
                                       {
                                          "conditionField":"$$FILE$$ad requests",
                                          "conditionComparator":"not in",
                                          "conditionValue":[
                                             "123"
                                          ],
                                          "defaultValue":100
                                       }
                                    ],
                                    "expression":"[$$FILE$$demand name]   *  [$$FILE$$demand id]   /  100"
                                 }
                              ],
                              "type":"addCalculatedField"
                           },
                           {
                              "fields":[
                                 {
                                    "field":"$$FILE$$demand id",
                                    "isOverride":false,
                                    "targetField":"ad_responses",
                                    "searchPattern":"(123)",
                                    "replacementValue":"xxx"
                                 }
                              ],
                              "type":"extractPattern"
                           }
                        ]
             */

            /*mapping field*/
            $newMapFields = [];
            foreach ($this->oldMapFields as $fieldFromFile => $fieldFromDataSet) {
                $newFieldFromFile = $fieldFromFile;
                if (!$this->isFieldFromFileMigrated($fieldFromFile)) {
                    $newFieldFromFile = $this->addPrefixForFieldFromFile($fieldFromFile);
                }

                $newMapFields[$newFieldFromFile] = $fieldFromDataSet;
            }

            $newTempFields = [];
            foreach ($this->oldTempFields as $oldTemporaryField) {
                $newTempField = $oldTemporaryField;
                if (!$this->isFieldFromTempMigrated($oldTemporaryField)) {
                    $newTempField = $this->addPrefixForFieldFromTemp($oldTemporaryField);
                }

                $newTempFields[] = $newTempField;
            }

            $filterFactory = new FilterFactory();
            foreach ($oldFilters as &$oldFilter) {
                $filterObject = $filterFactory->getFilter($oldFilter);
                if ($filterObject === null) {
                    continue;
                }

                $newFilterField = $filterObject->getField();
                if (!$this->isFieldFromFileMigrated($filterObject->getField())) {
                    $newFilterField = $this->addPrefixForFieldFromFile($filterObject->getField());
                }

                $oldFilter[ColumnFilterInterface::FIELD_NAME_FILTER_KEY] = $newFilterField;
            }


            $transformerFactory = new TransformerFactory();
            foreach ($oldTransformers as &$oldTransformer) {
                $transformObjects = $transformerFactory->getTransform($oldTransformer);

                /* Date or Number format */
                if ($transformObjects instanceof DateFormat || $transformObjects instanceof NumberFormat) {
                    continue;
                }

                /* Sort By or Group By */
                if ($transformObjects instanceof GroupByColumns) {
                    $newGroupByColumns = $this->addPrefixForArrayFields($transformObjects->getGroupByColumns());
                    $oldTransformer[CollectionTransformerInterface::FIELDS_KEY] = $newGroupByColumns;
                    continue;
                }

                if ($transformObjects instanceof SortByColumns) {
                    $newAscending = $this->addPrefixForArrayFields($transformObjects->getAscendingFields());
                    $newDescending = $this->addPrefixForArrayFields($transformObjects->getDescendingFields());
                    $newSortByTransform = new SortByColumns($newAscending, $newDescending);
                    $oldTransformer[CollectionTransformerInterface::FIELDS_KEY] = $newSortByTransform->getJsonTransformFieldsConfig();
                    continue;
                }

                /**
                 * @var CollectionTransformerInterface $transformObject
                 * other transform
                 */
                $newFieldsTransform = [];
                foreach ($transformObjects as $transformObject) {
                    if (!$transformObject instanceof CollectionTransformerInterface) {
                        continue;
                    }

                    /* add field */
                    if ($transformObject instanceof AddField) {
                        $newExpression = $this->convertToNewFormat($transformObject->getTransformValue());
                        $newAddFieldTransform = new AddField($transformObject->getColumn(), $newExpression, $transformObject->getType());
                        $newFieldsTransform[] = $newAddFieldTransform->getJsonTransformFieldsConfig();

                    } else if ($transformObject instanceof AddCalculatedField) {
                        $newFieldsTransform[] = $this->migrateAddCalculatedFields($transformObject);
                    } else if ($transformObject instanceof ReplaceText) {
                        $newFieldsTransform[] = $this->migrateReplaceText($transformObject);
                    } else if ($transformObject instanceof ExtractPattern) {
                        $newFieldsTransform[] = $this->migrateExtractPattern($transformObject);
                    } else if ($transformObject instanceof ComparisonPercent) {
                        $newFieldsTransform[] = $this->migrateComparisionPercent($transformObject);
                    } else if ($transformObject instanceof ConvertCase) {
                        $newFieldsTransform[] = $this->migrateConvertCase($transformObject);
                    } else if ($transformObject instanceof NormalizeText) {
                        $newFieldsTransform[] = $this->migrateNormalizeText($transformObject);
                    } else if ($transformObject instanceof Augmentation) {
                        /* augmentation */
                        if ($this->isFieldFromFileMigrated($transformObject->getSourceField())) {
                            continue;
                        }

                        $fieldFromFileWithPrefix = $this->addPrefixForFieldFromFile($transformObject->getSourceField());
                        $oldTransformer[Augmentation::MAP_CONDITION_KEY][Augmentation::DATA_SOURCE_SIDE] = $fieldFromFileWithPrefix;
                    } else if ($transformObject instanceof SubsetGroup) {
                        $newGroupFields = [];
                        /*migrate fields from file*/
                        foreach ($transformObject->getGroupFields() as $groupField) {
                            $newGroupField = $groupField;
                            if (!$this->isFieldFromFileMigrated($groupField) && !array_key_exists($groupField, $this->connectedDataSource->getDataSet()->getAllDimensionMetrics())) {
                                $newGroupField = $this->addPrefixForFieldFromFile($groupField);
                            }

                            $newGroupFields[] = $newGroupField;
                        }

                        $oldTransformer[SubsetGroup::GROUP_FIELD_KEY] = $newGroupFields;
                        $newSubsetGroupMapFields = [];
                        foreach ($transformObject->getMapFields() as $mapField) {
                            /*migrate fields from file*/
                            $newRightSide = $mapField[SubsetGroup::GROUP_DATA_SET_SIDE];
                            if (!$this->isFieldFromFileMigrated($newRightSide)) {
                                $newRightSide = $this->addPrefixForFieldFromFile($mapField[SubsetGroup::GROUP_DATA_SET_SIDE]);
                            }

                            $mapField[SubsetGroup::GROUP_DATA_SET_SIDE] = $newRightSide;

                            /*migrate fields from temp*/
                            $newLeftSide = $mapField[SubsetGroup::DATA_SOURCE_SIDE];
                            if (!$this->isFieldFromTempMigrated($newLeftSide) && in_array($newLeftSide, $this->oldTempFields)) {
                                $newLeftSide = $this->addPrefixForFieldFromTemp($mapField[SubsetGroup::DATA_SOURCE_SIDE]);
                            }

                            $mapField[SubsetGroup::DATA_SOURCE_SIDE] = $newLeftSide;

                            $newSubsetGroupMapFields[] = $mapField;
                        }

                        $oldTransformer[SubsetGroup::MAP_FIELDS_KEY] = $newSubsetGroupMapFields;
                    }
                }

                $oldTransformer[CollectionTransformerInterface::FIELDS_KEY] = $newFieldsTransform;
            }

            $migratedCount++;
            $connectedDataSource->setMapFields($newMapFields);
            $connectedDataSource->setTemporaryFields($newTempFields);
            $connectedDataSource->setFilters($oldFilters);
            $connectedDataSource->setTransforms($oldTransformers);
            $this->connectedDataSourceManager->save($connectedDataSource);
        }

        return $migratedCount;
    }

    private function isFieldFromFileMigrated($fieldFromFile)
    {
        return substr($fieldFromFile, 0, strlen(ConnectedDataSourceInterface::PREFIX_FILE_FIELD)) === ConnectedDataSourceInterface::PREFIX_FILE_FIELD;
    }

    private function isFieldFromTempMigrated($fieldFromFile)
    {
        return substr($fieldFromFile, 0, strlen(ConnectedDataSourceInterface::PREFIX_TEMP_FIELD)) === ConnectedDataSourceInterface::PREFIX_TEMP_FIELD;
    }

    private function convertToNewFormat($expression)
    {
        $regex = '/\[(.*?)\]/'; // $fieldsWithBracket = $matches[0];
        if (!preg_match_all($regex, $expression, $matches)) {
            return $expression;
        };

        $fields = $matches[1];
        $result = $expression;

        foreach ($fields as $field) {
            if ($this->isFieldFromFileMigrated($field)
                || array_key_exists($field, $this->connectedDataSource->getDataSet()->getAllDimensionMetrics())
            ) {
                continue;
            }

            if (!array_key_exists($field, $this->connectedDataSource->getDataSet()->getAllDimensionMetrics())
                && !array_key_exists($field, $this->connectedDataSource->getDataSource()->getDetectedFields())
            ) {
                continue;
            }

            if (in_array(sprintf('[%s]', $field), EmailMetadata::$internalFields)) {
                continue;
            }

            $replaceValue = sprintf('[%s]', ConnectedDataSourceInterface::PREFIX_FILE_FIELD . $field);
            $result = str_replace(sprintf('[%s]', $field), $replaceValue, $result);
        }

        return $result;
    }

    private function addPrefixForFieldFromFile($field)
    {
        return ConnectedDataSourceInterface::PREFIX_FILE_FIELD . $field;
    }

    private function addPrefixForFieldFromTemp($field)
    {
        return ConnectedDataSourceInterface::PREFIX_TEMP_FIELD . $field;
    }

    private function addPrefixForArrayFields(array $fields)
    {
        $newFields = [];
        foreach ($fields as $field) {
            if ($this->isFieldFromFileMigrated($field)) {
                $newFields[] = $field;
                continue;
            }

            $fieldFromFileWithPrefix = $this->addPrefixForFieldFromFile($field);
            $newFields[] = $fieldFromFileWithPrefix;
        }

        return $newFields;
    }

    private function migrateAddCalculatedFields(AddCalculatedField $addCalculatedFieldTransformObject)
    {
        /* add calculated field */
        $newDefaultValues = $addCalculatedFieldTransformObject->getDefaultValues();
        foreach ($newDefaultValues as &$defaultValue) {

            if (!is_array($defaultValue)) {
                continue;
            }

            if (!array_key_exists(AddCalculatedField::CONDITION_FIELD_KEY, $defaultValue)) {
                continue;
            }

            $conditionField = $defaultValue[AddCalculatedField::CONDITION_FIELD_KEY];
            if ($conditionField === AddCalculatedField::CONDITION_FIELD_CALCULATED_VALUE) {
                continue;
            }

            if (in_array($conditionField, $this->oldTempFields)) {
                if ($this->isFieldFromTempMigrated($conditionField)) {
                    continue;
                }

                $defaultValue[AddCalculatedField::CONDITION_FIELD_KEY] = $this->addPrefixForFieldFromTemp($conditionField);
            } else {
                if ($this->isFieldFromFileMigrated($conditionField)) {
                    continue;
                }

                $defaultValue[AddCalculatedField::CONDITION_FIELD_KEY] = $this->addPrefixForFieldFromFile($conditionField);
            }
        }

        $newExpression = $this->convertToNewFormat($addCalculatedFieldTransformObject->getExpression());
        $newAddCalculatedField = new AddCalculatedField($addCalculatedFieldTransformObject->getColumn(), $newExpression, $newDefaultValues);

        return $newAddCalculatedField->getJsonTransformFieldsConfig();
    }

    private function migrateComparisionPercent(ComparisonPercent $comparisonPercentTransformObject)
    {
        $newNumerator = $comparisonPercentTransformObject->getNumerator();
        if (!$this->isFieldFromFileMigrated($newNumerator)) {
            $newNumerator = $this->addPrefixForFieldFromFile($newNumerator);
        }

        $newDenominator = $comparisonPercentTransformObject->getDenominator();
        if (!$this->isFieldFromFileMigrated($newDenominator)) {
            $newDenominator = $this->addPrefixForFieldFromFile($newDenominator);
        }

        $comparisonPercentTransformObject->setNumerator($newNumerator);
        $comparisonPercentTransformObject->setDenominator($newDenominator);

        return $comparisonPercentTransformObject->getJsonTransformFieldsConfig();
    }

    private function migrateReplaceText(ReplaceText $replaceTextTransformObject)
    {
        /* replace text or extract pattern */
        $isMigrate = $this->isAllowMigratedField($replaceTextTransformObject->getField());

        if ($isMigrate) {
            $fieldFromFileWithPrefix = $this->addPrefixForFieldFromFile($replaceTextTransformObject->getField());
            $replaceTextTransformObject->setField($fieldFromFileWithPrefix);
        }

        return $replaceTextTransformObject->getJsonTransformFieldsConfig();
    }

    private function migrateExtractPattern(ExtractPattern $extractPatternTransformObject)
    {
        /* extract pattern */
        $isMigrate = $this->isAllowMigratedField($extractPatternTransformObject->getField());

        if ($isMigrate) {
            $fieldFromFileWithPrefix = $this->addPrefixForFieldFromFile($extractPatternTransformObject->getField());
            $extractPatternTransformObject->setField($fieldFromFileWithPrefix);
        }

        return $extractPatternTransformObject->getJsonTransformFieldsConfig();
    }

    private function migrateConvertCase(ConvertCase $convertCaseTransformObject)
    {
        /* convert case */
        $isMigrate = $this->isAllowMigratedField($convertCaseTransformObject->getField());

        if ($isMigrate) {
            $fieldFromFileWithPrefix = $this->addPrefixForFieldFromFile($convertCaseTransformObject->getField());
            $convertCaseTransformObject->setField($fieldFromFileWithPrefix);
        }

        return $convertCaseTransformObject->getJsonTransformFieldsConfig();
    }

    private function migrateNormalizeText(NormalizeText $normalizeTextTransformObject)
    {
        /* normalize text */
        $isMigrate = $this->isAllowMigratedField($normalizeTextTransformObject->getField());

        if ($isMigrate) {
            $fieldFromFileWithPrefix = $this->addPrefixForFieldFromFile($normalizeTextTransformObject->getField());
            $normalizeTextTransformObject->setField($fieldFromFileWithPrefix);
        }

        return $normalizeTextTransformObject->getJsonTransformFieldsConfig();
    }

    private function isAllowMigratedField($field)
    {
        $isMigrate = true;
        if ($this->isFieldFromFileMigrated($field)) {
            $isMigrate = false;
        }

        if (array_key_exists($field, $this->connectedDataSource->getDataSet()->getAllDimensionMetrics()) || in_array(sprintf('[%s]', $field), EmailMetadata::$internalFields)) {
            $isMigrate = false;
        }

        return $isMigrate;
    }
}