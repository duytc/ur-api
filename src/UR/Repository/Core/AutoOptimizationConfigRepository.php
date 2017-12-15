<?php


namespace UR\Repository\Core;


use Doctrine\ORM\EntityRepository;
use UR\Service\AutoOptimization\DataTrainingTableService;

class AutoOptimizationConfigRepository extends EntityRepository implements AutoOptimizationConfigRepositoryInterface
{
    public function deleteDataTrainingTableWhenDeleteAutoOptimizationConfig($autoOptimizationConfigId)
    {
        $conn = $this->_em->getConnection();
        $trainingTableName = sprintf(DataTrainingTableService::DATA_TRAINING_TABLE_NAME_PREFIX_TEMPLATE, $autoOptimizationConfigId);
        $sql = sprintf('DROP TABLE IF EXISTS `%s`;', $trainingTableName);
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $stmt->closeCursor();
    }
}