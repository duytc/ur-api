<?php


namespace UR\Behaviors;


use UR\Exception\InvalidArgumentException;
use UR\Service\Report\SqlBuilder;

trait JoinConfigUtilTrait
{
    public function getAliasForField($dataSetId, $field, $joinConfig)
    {
        foreach($joinConfig as $config) {
            foreach($config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS] as $join) {
                if ($join[SqlBuilder::JOIN_CONFIG_DATA_SET] == $dataSetId && $join[SqlBuilder::JOIN_CONFIG_FIELD] == $field) {
                    return $config[SqlBuilder::JOIN_CONFIG_OUTPUT_FIELD];
                }
            }
        }

        return sprintf('%s_%d', $field, $dataSetId);
    }

    public function findStartDatSet($joinConfig)
    {
        $fromDataSet = null;
        $count = count($joinConfig);
        if ($count < 2) {
            return $fromDataSet;
        }

        $occurrence = [];
        //calculate frequency of each data set id
        foreach ($joinConfig as $i=>$config) {
            $joinFields = $config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            foreach ($joinFields as $item) {
                if (isset($occurrence[$item[SqlBuilder::JOIN_CONFIG_DATA_SET]])) {
                    $occurrence[$item[SqlBuilder::JOIN_CONFIG_DATA_SET]]++;
                } else {
                    $occurrence[$item[SqlBuilder::JOIN_CONFIG_DATA_SET]] = 1;
                }
            }
        }

        $minValue = 1;
        foreach ($occurrence as $dataSet=>$value) {
            if ($value > $minValue) {
                $fromDataSet = $dataSet;
                $minValue = $value;
            }
        }

        return $fromDataSet;
    }


    public function findEndNodesForDataSet($joinConfig, $startDataSet, $startDataSets)
    {
        return array_filter($joinConfig, function($config) use($startDataSet, $startDataSets) {
           if (in_array($startDataSet, $config[SqlBuilder::JOIN_CONFIG_DATA_SETS])) {
               if (count($startDataSets) < 2) {
                   return true;
               }

               $count = count(array_diff($config[SqlBuilder::JOIN_CONFIG_DATA_SETS], $startDataSets));
               return $count > 0;
           }

            return false;
        });
    }

    /**
     * two arrays are considered to be equal if they are consisted of the same element,
     * regardless of the elements order
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
     * @param $joinConfig
     */
    public function normalizeJoinConfig(&$joinConfig)
    {
        $count = count($joinConfig);
        if ($count < 2) {
            return;
        }

        foreach ($joinConfig as $i=>&$config) {
            $joinFields = $config[SqlBuilder::JOIN_CONFIG_JOIN_FIELDS];
            $dataSets = array_map(function(array $item) {
                return $item[SqlBuilder::JOIN_CONFIG_DATA_SET];
            }, $joinFields);

            $config[SqlBuilder::JOIN_CONFIG_DATA_SETS] = $dataSets;
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
     * group join config pair by concatenated their input field and output field
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
    }
}