<?php


namespace UR\Bundle\ApiBundle\Behaviors;


use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use UR\Entity\Core\DataSet;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;

trait UpdateDataSetTotalRowTrait
{
    protected function updateTotalRow($dataSetId)
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
            ->setParameter('id', $dataSetId, Type::INTEGER)
        ;

        $qb->execute();
    }

    /**
     * @return EntityManagerInterface
     */
    protected abstract function getEntityManager();
}