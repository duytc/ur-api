<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;
use UR\Service\DataSet\FieldType;

class UpdateAutoOptimizationTrainingDataTableWhenConfigListener
{
    const DIMENSIONS_KEY = 'dimensions';
    const METRICS_KEY = 'metrics';
    const TRANSFORMS_KEY = 'transforms';

    protected $changedAutoOptimizationConfigs;

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $autoOptimizationConfig = $args->getEntity();


        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return;
        }

        $this->changedAutoOptimizationConfigs[] = $autoOptimizationConfig;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $autoOptimizationConfig = $args->getEntity();

        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return;
        }

        if ($args->hasChangedField(self::DIMENSIONS_KEY) || $args->hasChangedField(self::METRICS_KEY) || $args->hasChangedField(self::TRANSFORMS_KEY)) {
            $this->changedAutoOptimizationConfigs[] = $autoOptimizationConfig;
        }
    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();

        if (empty($this->changedAutoOptimizationConfigs)) {
            return;
        }

        foreach($this->changedAutoOptimizationConfigs as $autoOptimizationConfig) {
            if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
                continue;
            }

            $dataTrainingTableService = new DataTrainingTableService($em, '');
            $dataTrainingTable = $dataTrainingTableService->getDataTrainingTable($autoOptimizationConfig->getId());

            if (!$dataTrainingTable instanceof Table) {
                continue; // does not exist => do not sync data training table
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
                // TODO: Use quote $em->getConnection()->quoteIdentifier()
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

            try {
                // get query alter table
                $dataTrainingTableService->syncSchema($schema);
            } catch (\Exception $e) {
                // TODO 
            }

            $em->persist($autoOptimizationConfig);
        }

        $this->changedAutoOptimizationConfigs = [];

        $em->flush();
    }
}