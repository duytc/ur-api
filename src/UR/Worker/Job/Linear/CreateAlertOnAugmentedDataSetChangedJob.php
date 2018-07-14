<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Job\JobInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;
use UR\Service\Alert\DataSet\DataAugmentedDataSetChangeAlert;
use UR\Service\Alert\DataSet\DataSetAlertFactory;
use UR\Service\Alert\DataSet\DataSetAlertInterface;
use UR\Service\Alert\ProcessAlertInterface;

class CreateAlertOnAugmentedDataSetChangedJob implements JobInterface
{
    const JOB_NAME = 'CreateAlertOnAugmentedDataSetChangedJob';

    const DATA_SET_ID = 'data_set_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  LinkedMapDataSetRepositoryInterface */
    private $linkedMapDataSetRepository;

    /** @var  ProcessAlertInterface */
    private $processAlert;

    /** @var  DataSetAlertFactory */
    private $dataSetAlertFactory;

    /** @var  AlertManagerInterface */
    private $alertManager;

    public function __construct(
        LoggerInterface $logger,
        DataSetManagerInterface $dataSetManager,
        AlertManagerInterface $alertManager,
        LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository,
        ProcessAlertInterface $processAlert
    )
    {
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->alertManager = $alertManager;
        $this->linkedMapDataSetRepository = $linkedMapDataSetRepository;
        $this->processAlert = $processAlert;
        $this->dataSetAlertFactory = new DataSetAlertFactory();
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);

        if (!is_integer($dataSetId)) {
            return;
        }

        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }
        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSet($dataSet);
        if (empty($linkedMapDataSets)) {
            return;
        }

        foreach ($linkedMapDataSets as $linkedMapDataSet) {
            if (!$linkedMapDataSet instanceof LinkedMapDataSetInterface) {
                continue;
            }

            $dataSet = $linkedMapDataSet->getConnectedDataSource()->getDataSet();
            $this->createAlertDataSet($dataSet);
        }
    }

    /**
     * @param DataSetInterface $dataSet
     *
     */
    private function createAlertDataSet(DataSetInterface $dataSet)
    {
        if(!$this->isShouldCreateNewAlertForDataSet($dataSet)) {
            return;
        }

        /**
         * @var DataAugmentedDataSetChangeAlert $dataAugmentedDataSetChangeAlert
         */
        $dataAugmentedDataSetChangeAlert = $this->dataSetAlertFactory->getAlert(
            DataSetAlertInterface::ALERT_TYPE_VALUE_DATA_AUGMENTED_DATA_SET_CHANGED,
            AlertInterface::ALERT_CODE_DATA_AUGMENTED_DATA_SET_CHANGED,
            $dataSet
        );

        $this->processAlert->createAlert(
            $dataAugmentedDataSetChangeAlert->getAlertCode(),
            $dataSet->getPublisherId(),
            $dataAugmentedDataSetChangeAlert->getDetails(),
            null,
            null,
            $dataSet->getId()
        );
    }

    /**
     * @param DataSetInterface $dataSet
     * @return boolean
     */
    public function isShouldCreateNewAlertForDataSet(DataSetInterface $dataSet)
    {
        $alerts = $this->alertManager->getUnreadAlertByDataSet($dataSet);
        if(!empty($alerts) && is_array($alerts)) {
            $alert = $alerts[0];
            if($alert instanceof AlertInterface) {
                $alert->setCreatedDate(new \DateTime('now'));
                $this->alertManager->save($alert);

                return false;
            }
        }

        return true;
    }
}