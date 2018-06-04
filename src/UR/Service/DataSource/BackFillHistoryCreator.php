<?php


namespace UR\Service\DataSource;

use DateTime;
use Doctrine\Common\Collections\Collection;
use UR\DomainManager\DataSourceIntegrationBackfillHistoryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Entity\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceIntegrationBackfillHistoryRepositoryInterface;

class BackFillHistoryCreator implements BackFillHistoryCreatorInterface
{
    /**
     * @var DataSourceIntegrationBackfillHistoryManagerInterface
     */
    protected $backFillHistoryManager;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /** @var DataSourceIntegrationBackfillHistoryRepositoryInterface  $backFillHistoryRepository*/
    private $backFillHistoryRepository;

    /**
     * BackFillDataSource constructor.
     * @param DataSourceIntegrationBackfillHistoryManagerInterface $backFillHistoryManager
     * @param DataSourceManagerInterface $dataSourceManager
     * @param DataSourceIntegrationBackfillHistoryRepositoryInterface $backFillHistoryRepository
     */
    public function __construct(DataSourceIntegrationBackfillHistoryManagerInterface $backFillHistoryManager, DataSourceManagerInterface $dataSourceManager, DataSourceIntegrationBackfillHistoryRepositoryInterface $backFillHistoryRepository)
    {
        $this->backFillHistoryManager = $backFillHistoryManager;
        $this->dataSourceManager = $dataSourceManager;
        $this->backFillHistoryRepository = $backFillHistoryRepository;
    }

    /**
     * @inheritdoc
     */
    public function createBackfillForDataSource(DataSourceInterface $dataSource)
    {
        $publisher = $dataSource->getPublisher();
        if (!$publisher instanceof PublisherInterface || !$publisher->getUser()->isEnabled() || !$dataSource->getEnable()) {
            return '';
        }
        if(!$this->isActiveLessTwoWeeks($dataSource->getLastActivity())){
            return '';
        }
        $dataSourceIntegrations = $dataSource->getDataSourceIntegrations();
        $dataSourceIntegrations = $dataSourceIntegrations instanceof Collection ? $dataSourceIntegrations->toArray() : $dataSourceIntegrations;
        if (!is_array($dataSourceIntegrations) || empty($dataSourceIntegrations)) {
            return '';
        }

        $dataSourceIntegration = end($dataSourceIntegrations);

        if ($dataSource->getBackfillMissingDateRunning() == true) {
            $this->backFillHistoryManager->deleteCurrentAutoCreateBackFillHistory($dataSource);
        }
        $qb = $this->backFillHistoryRepository->findByBackFillNotExecutedForDataSource($dataSourceIntegration->getId());
        $notExecutedBackFills = $qb->getQuery()->getResult();

        if (!is_array($dataSource->getMissingDate()) || empty($dataSource->getMissingDate())) {
            return '';
        }

        $missingDateRanges = $dataSource->getMissingDate();
        foreach ($notExecutedBackFills as $notExecutedBackFill) {
            if (!$notExecutedBackFill instanceof DataSourceIntegrationBackfillHistoryInterface) {
                continue;
            }
            $startDate = $notExecutedBackFill->getBackFillStartDate();
            $endDate = $notExecutedBackFill->getBackFillEndDate();
            foreach($missingDateRanges as $key => $missingDate){
                if($this->check_in_range($startDate, $endDate, $missingDate)){
                    unset($missingDateRanges[$key]);
                }
            }

        }

        if(empty($missingDateRanges)){
            return '';
        }
        sort($missingDateRanges);

        $startMissingDate = reset($missingDateRanges);
        $lastestMissingDate = '';
        $createBackfill = false;
        $endDateBackfill = '';

        //If missing dates = ["2017-02-20", "2017-02-21","2017-02-23", "2017-02-25"]
        //We need create 3 back fill 20-21, 23-23 and 25-25
        for ($i = 0; $i < count($missingDateRanges); $i++) {
            if ($i == 0) {
                $lastestMissingDate = $missingDateRanges[0];
            }
            if ($i >= 1) {
                if ($lastestMissingDate < $missingDateRanges[$i] && $missingDateRanges[$i] == date('Y-m-d', strtotime($lastestMissingDate . ' +1 day'))) {
                    $lastestMissingDate = $missingDateRanges[$i];
                    if ($i == count($missingDateRanges) - 1) {
                        $createBackfill = true;
                        //set start and end date
                        $startDateBackfill = $startMissingDate;
                        $endDateBackfill = $lastestMissingDate;
                        //create backfill
                        $this->createBackFill($startDateBackfill, $endDateBackfill, $dataSourceIntegration);
                    }
                } else {
                    $createBackfill = true;
                    //set start and end date
                    $startDateBackfill = $startMissingDate;
                    $endDateBackfill = $lastestMissingDate;
                    //create backfill
                    $this->createBackFill($startDateBackfill, $endDateBackfill, $dataSourceIntegration);

                    //set start missing date
                    $startMissingDate = $lastestMissingDate = $missingDateRanges[$i];
                }
            }
        }

        if ($lastestMissingDate > $endDateBackfill) {
            //Current $lastestMissingDate = "2017-02-25", $endDateBackfill = "2017-02-23"
            //Create back fill from "2017-02-25" to "2017-02-25"
            $createBackfill = true;
            $this->createBackFill($lastestMissingDate, $lastestMissingDate, $dataSourceIntegration);
        }

        if ($createBackfill == false) {
            //set start and end date
            $startDateBackfill = $startMissingDate;
            $endDateBackfill = $missingDateRanges[count($missingDateRanges) - 1];
            //create backfill
            $this->createBackFill($startDateBackfill, $endDateBackfill, $dataSourceIntegration);

        }

        // change backfillMissingDateRunning to true
        $dataSource->setBackfillMissingDateRunning(true);
        $this->dataSourceManager->save($dataSource);
        return '';
    }

    /**
     * @param $startDateBackFill
     * @param $endDateBackFill
     * @param $dataSourceIntegration
     */
    protected function createBackFill($startDateBackFill, $endDateBackFill, $dataSourceIntegration)
    {
        //create backfill
        $backFillHistory = new DataSourceIntegrationBackfillHistory();
        $backFillHistory->setDataSourceIntegration($dataSourceIntegration);
        $backFillHistory->setBackFillStartDate(DateTime::createFromFormat('Y-m-d', $startDateBackFill));
        $backFillHistory->setBackFillEndDate(DateTime::createFromFormat('Y-m-d', $endDateBackFill));
        $backFillHistory->setAutoCreate(true);

        $this->backFillHistoryManager->save($backFillHistory);
    }


    /**
     * @param $lastActivity
     * @return bool
     */
    private function isActiveLessTwoWeeks($lastActivity)
    {
        $lastActivity = $lastActivity->format('Y-m-d H:i:s');
        $lastActivity = new DateTime($lastActivity, new \DateTimeZone('UTC'));
        if(!$lastActivity instanceof DateTime){
            return false;
        }
        $now = new DateTime('now', new \DateTimeZone('UTC'));
        if($lastActivity->modify( '+ 14 days') < $now){
            return false;
        }

        return true;
    }

    private function check_in_range($startDate, $endDate, $missingDate)
    {
        $startDate = $startDate->format('Y-m-d H:i:s');
        $endDate = $endDate->format('Y-m-d H:i:s');
        $startDate = new DateTime($startDate, new \DateTimeZone('UTC'));
        $endDate = new DateTime($endDate, new \DateTimeZone('UTC'));
        $missingDate = new DateTime($missingDate, new \DateTimeZone('UTC'));
        if($missingDate >= $startDate && $missingDate <= $endDate){
            return true;
        }
        return false;
    }
}