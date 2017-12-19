<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\DataTrainingTableService;

class UpdateAutoOptimizationTrainingDataTableWhenSelectDimensionsMetricsListener
{
    const DIMENSIONS_KEY = 'dimensions';
    const METRICS_KEY = 'metrics';

    protected $changedAutoOptimizationConfigs;

    /** @var  EntityManagerInterface */
    private $em;

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $optimizationConfigDataSet = $args->getEntity();
        $em = $args->getEntityManager();
        $this->em = $em;

        if (!$optimizationConfigDataSet instanceof AutoOptimizationConfigDataSetInterface) {
            return;
        }

        if ($args->hasChangedField(self::DIMENSIONS_KEY) || $args->hasChangedField(self::METRICS_KEY)) {
            $autoOptimizationConfig = $optimizationConfigDataSet->getAutoOptimizationConfig();
            $this->changedAutoOptimizationConfigs[] = $optimizationConfigDataSet;

            if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
                return;
            }

            $dataTrainingTableService = new DataTrainingTableService($em, '');
            $dataTrainingTable = $dataTrainingTableService->getDataTrainingTable($autoOptimizationConfig->getId());

            if (!$dataTrainingTable instanceof Table) {
                return; // does not exist => do not sync data training table
            }

            // keep default columns(primary key), delete all current columns
            $allColumnsCurrent = $dataTrainingTable->getColumns();
            foreach ($allColumnsCurrent as $key => $value){
                $columnName = $value->getName();
                if ($columnName == DataTrainingTableService::COLUMN_ID) {
                    continue;
                }
                $dataTrainingTable->dropColumn($columnName);
            }
            // get all columns
            $allColumns = $dataTrainingTableService->getDimensionsMetricsAndTransformField($autoOptimizationConfig);

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
        }
    }
}