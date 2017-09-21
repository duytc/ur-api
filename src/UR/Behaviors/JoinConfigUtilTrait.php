<?php


namespace UR\Behaviors;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\JoinBy\JoinConfigInterface;
use UR\Domain\DTO\Report\JoinBy\JoinFieldInterface;
use UR\Exception\InvalidArgumentException;
use UR\Service\Report\SqlBuilder;

trait JoinConfigUtilTrait
{
    public function getAliasForUpdateField($dataSetId, $field, $joinConfig, $dataSetMetrics = [], &$isDimension)
    {
        $alias = null;
        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            $matchIndex = -1;
            /** @var JoinFieldInterface $join */
            foreach($config->getJoinFields() as $join) {
                $inputFields = explode(',', $join->getField());
                foreach ($inputFields as $i => $inputField) {
                    if ($field == $inputField && $join->getDataSet() == $dataSetId) {
                        $outputFields = explode(',', $config->getOutputField());
                        $alias = $outputFields[$i];
                        $matchIndex = $i;
                        break 2;
                    }
                }
            }

            if ($matchIndex == -1) {
                continue;
            }

            /** @var JoinFieldInterface $join */
            foreach($config->getJoinFields() as $join) {
                if ($join->getDataSet() == $dataSetId) {
                    continue;
                }

                $inputFields = explode(',', $join->getField());
                $otherField = $inputFields[$matchIndex];
                $otherField = sprintf('%s_%d', $otherField, $join->getDataSet());
                if (in_array($otherField, $dataSetMetrics) && !$isDimension) {
                    $isDimension = false;
                } else {
                    $isDimension = true;
                }

                break 2;
            }
        }

        if ($alias) {
            return $alias;
        }

        return sprintf('%s_%d', $field, $dataSetId);
    }


    public function getAliasForField($dataSetId, $field, $joinConfig)
    {
        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            /** @var JoinFieldInterface $join */
            foreach($config->getJoinFields() as $join) {
                $fields = explode(',', $join->getField());
                $outputFields = explode(',', $config->getOutputField());
                if ($join->getDataSet() == $dataSetId && in_array($field, $fields)) {
                    $fieldIndexes = array_flip($fields);
//                    if ($config->isVisible()) {
                    return $outputFields[$fieldIndexes[$field]];
//                    }

//                    return null;
                }
            }
        }

        return sprintf('%s_%d', $field, $dataSetId);
    }

    /**
     * @param $joinConfig
     * @param $startDataSet
     * @param $startDataSets
     * @return array
     */
    public function findEndNodesForDataSet($joinConfig, $startDataSet, $startDataSets)
    {
        return array_filter($joinConfig, function(JoinConfigInterface $config) use($startDataSet, $startDataSets) {
           if (in_array($startDataSet, $config->getDataSets())) {
               if (count($startDataSets) < 2) {
                   return true;
               }

               $count = count(array_diff($config->getDataSets(), $startDataSets));
               return $count > 0;
           }

            return false;
        });
    }

    /**
     * two arrays are considered to be equal if they are consisted of the same element,
     * regardless of the elements order
     *
     * @param $a
     * @param $b
     * @return bool
     */
    public function compareArray($a, $b)
    {
        return !array_diff($a, $b) && !array_diff($b, $a);
    }

    /**
     * group join configs which is consisted of the same data set pair
     *
     * @param array $joinConfig
     * @param array $dataSets
     */
    public function normalizeJoinConfig(array &$joinConfig, array $dataSets)
    {
        foreach ($joinConfig as $config) {
            if (!array_key_exists(SqlBuilder::JOIN_CONFIG_JOIN_FIELDS, $config) ||
                !array_key_exists(SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD, $config)
            ) {
                throw new InvalidArgumentException('missing either "joinFields" or "outputField in join config"');
            }

            foreach ($config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS] as $dataSet) {
                if (!array_key_exists(SqlBuilder::JOIN_CONFIG_DATA_SET, $dataSet) ||
                    !array_key_exists(SqlBuilder::JOIN_CONFIG_FIELD, $dataSet)
                ) {
                    throw new InvalidArgumentException('missing either "field" or "dataSet in join config"');
                }
            }
        }

        $count = count($joinConfig);
        if ($count < 2 && count($dataSets) < 2) {
            return;
        }

        $allDataSetsId = array_map(function(DataSet $dataSet) {
            return $dataSet->getDataSetId();
        }, $dataSets);

        $joinDataSetsId = [];
        foreach ($joinConfig as &$config) {
            $joinFields = $config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            foreach ($joinFields as $joinField) {
                $joinDataSetsId[] = $joinField[SqlBuilder::JOIN_CONFIG_DATA_SET];
            }

            $dataSets = array_map(function(array $item) {
                return $item[SqlBuilder::JOIN_CONFIG_DATA_SET];
            }, $joinFields);

            $config[SqlBuilder::JOIN_CONFIG_DATA_SETS] = $dataSets;
        }

        if (count(array_diff($allDataSetsId, $joinDataSetsId)) > 0) {
            throw new InvalidArgumentException("There's seem to be some data set is missing from the join config");
        }

        //group join config which have the same data sets
        $toBeRemoved = [];
        for ($i=0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->compareArray($joinConfig[$i][SqlBuilder::JOIN_CONFIG_DATA_SETS], $joinConfig[$j][SqlBuilder::JOIN_CONFIG_DATA_SETS])) {
                    $this->groupJoinConfigItem($joinConfig[$i], $joinConfig[$j]);
                    $toBeRemoved[] = $j;
                }
            }
        }

        foreach($toBeRemoved as $k) {
            unset($joinConfig[$k]);
        }
    }

    /**
     * check if the join configs is circular
     *
     * @param $joinConfig
     * @return bool
     */
    public function isCircularJoinConfig(array $joinConfig)
    {
        if (count($joinConfig) < 2) {
            return false;
        }
        
        $occurrence = [];

        /** @var JoinConfigInterface $config */
        foreach ($joinConfig as $config) {
            $joinFields = $config->getJoinFields();
            /** @var JoinFieldInterface $joinField */
            foreach ($joinFields as $joinField) {
                $joinDataSetsId = $joinField->getDataSet();
                if (!array_key_exists($joinDataSetsId, $occurrence)) {
                    $occurrence[$joinDataSetsId] = 1;
                } else {
                    $occurrence[$joinDataSetsId]++;
                }
            }
        }

        $isCircular = true;
        foreach($occurrence as $value) {
            if ($value != 2) {
                return false;
            }
        }

        return $isCircular;
    }

    /**
     * group join config pair by concatenated their input field and output field
     *
     * @param $source
     * @param $toBeGrouped
     */
    private function groupJoinConfigItem(&$source, $toBeGrouped)
    {
        usort($source[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS], function ($a, $b) {
            return filter_var($a[SqlBuilder::JOIN_CONFIG_DATA_SET], FILTER_VALIDATE_INT) > filter_var($b[SqlBuilder::JOIN_CONFIG_DATA_SET], FILTER_VALIDATE_INT);
        });

        usort($toBeGrouped[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS], function ($a, $b) {
            return filter_var($a[SqlBuilder::JOIN_CONFIG_DATA_SET], FILTER_VALIDATE_INT) > filter_var($b[SqlBuilder::JOIN_CONFIG_DATA_SET], FILTER_VALIDATE_INT);
        });

        foreach($source[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS] as $i => &$joinField) {
            $joinField[SqlBuilder::JOIN_CONFIG_FIELD] = $joinField[SqlBuilder::JOIN_CONFIG_FIELD] . ',' . $toBeGrouped[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS][$i][SqlBuilder::JOIN_CONFIG_FIELD];
        }

        $source[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD] = $source[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD] . ',' . $toBeGrouped[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD];
        $source[SqlBuilder::JOIN_CONFIG_VISIBLE] = sprintf('%b,%b', $source[SqlBuilder::JOIN_CONFIG_VISIBLE], $toBeGrouped[SqlBuilder::JOIN_CONFIG_VISIBLE]);
        $source[SqlBuilder::JOIN_CONFIG_MULTIPLE] = true;

    }
}