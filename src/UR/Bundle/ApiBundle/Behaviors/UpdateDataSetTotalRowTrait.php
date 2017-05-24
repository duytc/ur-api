<?php


namespace UR\Bundle\ApiBundle\Behaviors;


use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Entity\Core\DataSet;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;

trait UpdateDataSetTotalRowTrait
{
    protected function updateDataSetTotalRow($dataSetId)
    {
        $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSetId);
        $conn = $this->getEntityManager()->getConnection();
        $qb = new QueryBuilder($conn);
        $totalRow = $qb->select("count(__id)")
            ->from($tableName)
            ->where(sprintf('%s IS NULL', DataSetInterface::OVERWRITE_DATE))
            ->execute()
            ->fetchColumn(0);

        $tableName = $this->getEntityManager()->getClassMetadata(DataSet::class)->getTableName();
        $qb = new QueryBuilder($conn);
        $qb->update($tableName, 'dts')
            ->set('dts.total_row', $totalRow)
            ->where('dts.id = :id')
            ->setParameter('id', $dataSetId, Type::INTEGER);

        $qb->execute();
    }

    protected function updateConnectedDataSourceTotalRow(DataSetInterface $dataSet)
    {
        foreach ($dataSet->getConnectedDataSources() as $connectedDataSource) {
            $dataSetId = $dataSet->getId();
            $tableName = sprintf(Synchronizer::DATA_IMPORT_TABLE_NAME_PREFIX_TEMPLATE, $dataSetId);
            $conn = $this->getEntityManager()->getConnection();
            $conn->beginTransaction();
            $qb = new QueryBuilder($conn);
            $totalRow = $qb->select("count(__id)")
                ->from($tableName)
                ->where(sprintf('%s IS NULL', DataSetInterface::OVERWRITE_DATE))
                ->andWhere(sprintf('%s=:%s', '__connected_data_source_id', 'connectedDataSourceId'))
                ->setParameter('connectedDataSourceId', $connectedDataSource->getId(), Type::INTEGER)
                ->execute()
                ->fetchColumn(0);

            $tableName = $this->getEntityManager()->getClassMetadata(ConnectedDataSource::class)->getTableName();
            $qb = new QueryBuilder($conn);
            $qb->update($tableName, 'dts')
                ->set('dts.total_row', $totalRow)
                ->where('dts.id = :id')
                ->setParameter('id', $connectedDataSource->getId(), Type::INTEGER);

            $qb->execute();
            $conn->commit();
            $conn->close();
        }
    }

    /**
     * @return EntityManagerInterface
     */
    protected abstract function getEntityManager();
}