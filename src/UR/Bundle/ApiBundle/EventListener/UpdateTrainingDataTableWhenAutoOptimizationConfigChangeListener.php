<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;
use UR\Service\DataSet\FieldType;

class UpdateTrainingDataTableWhenAutoOptimizationConfigChangeListener
{
    const TRANSFORMS_KEY = 'transforms';

    protected $changedAutoOptimizationConfigs;

    /** @var  EntityManagerInterface */
    private $em;

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
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

        if ($args->hasChangedField(DataSetInterface::DIMENSIONS_COLUMN) || $args->hasChangedField(DataSetInterface::METRICS_COLUMN) || $args->hasChangedField(self::TRANSFORMS_KEY)) {
            $this->changedAutoOptimizationConfigs[] = $autoOptimizationConfig;
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $autoOptimizationConfig = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            return;
        }

        $synchronize = new DataTrainingTableService($em, '');
        $synchronize->deleteDataTrainingTable($autoOptimizationConfig->getId());
    }

    public function postFlush(PostFlushEventArgs $args)
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
            // keep default columns(primary key), delete all current columns
            $allColumnsCurrent = $dataTrainingTable->getColumns();
            foreach ($allColumnsCurrent as $key => $value) {
                $columnName = $value->getName();
                if ($columnName == DataTrainingTableService::COLUMN_ID) {
                    continue;
                }
                $dataTrainingTable->dropColumn($columnName);
            }

            // get all columns
            $allColumns = $dataTrainingTableService->getDimensionsMetricsAndTransformField($autoOptimizationConfig);
            $allColumns[AutoOptimizationConfigInterface::IDENTIFIER_COLUMN] = FieldType::TEXT;

            foreach ($allColumns as $fieldName => $fieldType) {
                $dataTrainingTable = $dataTrainingTableService->addFieldForTable($dataTrainingTable, $fieldName, $fieldType);
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
}