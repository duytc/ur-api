<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
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

    /** @var  EntityManagerInterface */
    private $em;

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
        $this->em = $em;

        if (empty($this->changedAutoOptimizationConfigs)) {
            return;
        }

        $changedAutoOptimizationConfigs = $this->changedAutoOptimizationConfigs;
        $this->changedAutoOptimizationConfigs = [];

        foreach ($changedAutoOptimizationConfigs as $autoOptimizationConfig) {
            if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
                continue;
            }

            $dataTrainingTableService = new DataTrainingTableService($em, '');
            $dataTrainingTable = $dataTrainingTableService->createEmptyDataTrainingTable($autoOptimizationConfig);

            if (!$dataTrainingTable instanceof Table) {
                continue; // does not exist => do not sync data training table
            }

            // get all columns
            $allColumns = $dataTrainingTableService->getDimensionsMetricsAndTransformField($autoOptimizationConfig);

            foreach ($allColumns as $fieldName => $fieldType) {
                if (!$dataTrainingTable->hasColumn($fieldName)) {
                    $dataTrainingTable = $this->addFieldForTable($dataTrainingTable, $fieldName, $fieldType);
                }
            }

            $schema = new Schema([$dataTrainingTable]);

            try {
                // get query alter table
                $dataTrainingTableService->syncSchema($schema);
            } catch (\Exception $e) {
                // TODO 
            }

            $em->persist($autoOptimizationConfig);
        }

        $em->flush();
    }

    /**
     * @param Table $dataTrainingTable
     * @param $fieldName
     * @param $fieldType
     * @return Table
     */
    private function addFieldForTable(Table $dataTrainingTable, $fieldName, $fieldType)
    {
        $fieldName = $this->em->getConnection()->quoteIdentifier($fieldName);

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

        return $dataTrainingTable;
    }
}