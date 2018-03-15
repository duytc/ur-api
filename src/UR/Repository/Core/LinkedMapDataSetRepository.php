<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

class LinkedMapDataSetRepository extends EntityRepository implements LinkedMapDataSetRepositoryInterface
{
    public function getByMapDataSet(DataSetInterface $dataSet)
    {
        return $this->getByMapDataSetId($dataSet->getId());
    }

    public function getByMapDataSetId($dataSetId)
    {
        return $this->createQueryBuilder('l')
            ->where('l.mapDataSet = :dataSet')
            ->setParameter('dataSet', $dataSetId)->getQuery()->getResult();
    }

    public function override($mapDataSet, ConnectedDataSourceInterface $connectedDataSource, array $mappedFields)
    {
        $sql = 'INSERT INTO core_linked_map_data_set (connected_data_source_id, map_data_set_id, mapped_fields)
                VALUES (:connectedDataSource, :mapDataSet, :mappedFields)
                ON DUPLICATE KEY UPDATE
                mapped_fields = :mappedFields';
        $connection = $this->getEntityManager()->getConnection();
        $qb = $connection->prepare($sql);

        $qb->bindValue('connectedDataSource', $connectedDataSource->getId(), Type::INTEGER);
        $qb->bindValue('mapDataSet', $mapDataSet, Type::INTEGER);
        $qb->bindValue('mappedFields', $mappedFields, Type::JSON_ARRAY);

        $connection->beginTransaction();
        try {
            if (false === $qb->execute()) {
                throw new \Exception('Execute error');
            }
            $connection->commit();
        } catch (\Exception $ex) {
            $connection->rollBack();
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteByConnectedDataSource($connectedDataSourceId)
    {
        $connection = $this->getEntityManager()->getConnection();
        $tableName = $this->getEntityManager()->getClassMetadata(LinkedMapDataSet::class)->getTableName();
        $sql= sprintf('DELETE FROM %s WHERE %s = :connectedDataSourceId', $connection->quoteIdentifier($tableName), $connection->quoteIdentifier('connected_data_source_id'));;

        $qb = $connection->prepare($sql);

        $qb->bindValue('connectedDataSourceId', $connectedDataSourceId, Type::INTEGER);

        $connection->beginTransaction();
        try {
            if (false === $qb->execute()) {
                throw new \Exception('Execute error');
            }
            $connection->commit();
        } catch (\Exception $ex) {
            $connection->rollBack();
            throw $ex;
        }
    }
}