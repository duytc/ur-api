<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;
use UR\Service\DataSet\FieldType;

class UpdateAutoOptimizationTrainingDataTableWhenSelectDimensionsMetricsListener
{
    const DIMENSIONS_KEY = 'dimensions';
    const METRICS_KEY = 'metrics';

    protected $changedAutoOptimizationConfigs;

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $optimizationConfigDataSet = $args->getEntity();

        if (!$optimizationConfigDataSet instanceof AutoOptimizationConfigDataSetInterface) {
            return;
        }

        if ($args->hasChangedField(self::DIMENSIONS_KEY) || $args->hasChangedField(self::METRICS_KEY)) {
            $autoOptimizationConfig = $optimizationConfigDataSet->getAutoOptimizationConfig();
            $this->changedAutoOptimizationConfigs[] = $optimizationConfigDataSet;
            $em = $args->getEntityManager();


            if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
                return;
            }

            $dataTrainingTableService = new DataTrainingTableService($em, '');
            $dataTrainingTable = $dataTrainingTableService->getDataTrainingTable($autoOptimizationConfig->getId());

            if (!$dataTrainingTable instanceof Table) {
                return; // does not exist => do not sync data training table
            }

            // get all columns
            $allColumnsCurrent = $dataTrainingTable->getColumns();
            //keep default columns(primary key); remove columns of dimensions, metrics and the columns do not use
            foreach ($allColumnsCurrent as $key => $value){
                $columnName = $value->getName();
                if (preg_match('/__/', $columnName)) {
                    continue;
                } else {
                    $dataTrainingTable->dropColumn($columnName);
                }
            }

            // get all columns
            $dimensionsMetricsAndTransformField = $dataTrainingTableService->getDimensionsMetricsAndTransformField($autoOptimizationConfig);

            foreach ($dimensionsMetricsAndTransformField as $fieldName => $fieldType) {
                $fieldName = '`'.$fieldName.'`';
                if ($fieldType === FieldType::NUMBER) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                    $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
                } else if ($fieldType === FieldType::DECIMAL) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                    $dataTrainingTable->addColumn($fieldName, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
                } else if ($fieldType === FieldType::LARGE_TEXT) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                    $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => DataTrainingTableService::FIELD_LENGTH_LARGE_TEXT]);
                } else if ($fieldType === FieldType::TEXT) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                    $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null, 'length' => DataTrainingTableService::FIELD_LENGTH_TEXT]);
                } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                    $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                    $dataTrainingTable->addColumn($fieldName, $colType, ['notnull' => false, 'default' => null]);
                } else {
                    $dataTrainingTable->addColumn($fieldName, $fieldType, ['notnull' => false, 'default' => null]);
                }
            }

            $tables[] = $dataTrainingTable;
            $schema = new Schema($tables);

            // get query alter table
            $dataTrainingTableService->syncSchema($schema);
        }
    }
}