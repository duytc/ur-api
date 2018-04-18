<?php

namespace UR\Worker\Job\Linear;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\MapBuilderConfig;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Repository\Core\MapBuilderConfigRepositoryInterface;

class UpdateMapDataSetWhenAlterDataSetSubJob implements SubJobInterface
{
    const JOB_NAME = 'updateMapDataSetWhenAlterDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';

    const NEW_FIELDS = 'new_fields';
    const UPDATE_FIELDS = 'update_fields';
    const DELETED_FIELDS = 'deleted_fields';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $em)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);
        $updateColumns = $params->getRequiredParam(self::UPDATE_FIELDS);
        $deletedColumns = $params->getRequiredParam(self::DELETED_FIELDS);
        /** @var MapBuilderConfigRepositoryInterface $mapBuilderConfigRepository */
        $mapBuilderConfigRepository = $this->em->getRepository(MapBuilderConfig::class);
        $dataSetRepository = $this->em->getRepository(DataSet::class);
        /** @var DataSetInterface $dataSet */
        $dataSet = $dataSetRepository->find($dataSetId);

        $mapBuilderConfigs = $mapBuilderConfigRepository->getByDataSet($dataSet);
        foreach ($mapBuilderConfigs as $config) {
            if (!$config instanceof MapBuilderConfigInterface) {
                continue;
            }
            $mapFields = $config->getMapFields();
            foreach ($mapFields as $key => $field) {
                if (isset($updateColumns[$key])) {
                    $mapFields[$updateColumns[$key]] = $field;
                    unset($mapFields[$key]);
                }

                if (in_array($key, $deletedColumns)) {
                    unset($mapFields[$key]);
                }
            }

            $config->setMapFields($mapFields);
            $this->em->merge($config);
        }

        $mapBuilderConfigs = $mapBuilderConfigRepository->getByMapDataSet($dataSet);
        foreach ($mapBuilderConfigs as $config) {
            if (!$config instanceof MapBuilderConfigInterface) {
                continue;
            }
            $mapFields = $config->getMapFields();
            $dataSet = $config->getDataSet();
            foreach ($mapFields as $key => $field) {
                if (isset($updateColumns[$field])) {
                    $mapFields[$key] = $updateColumns[$field];
                }

                if (array_key_exists($field, $deletedColumns)) {
                    unset($mapFields[$key]);
                    $dataSet->increaseNumChanges();
                }
            }

            $config->setMapFields($mapFields);
            $this->em->merge($config);
            $this->em->merge($dataSet);
        }

        $this->em->flush();
    }
}