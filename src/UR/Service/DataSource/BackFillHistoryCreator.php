<?php


namespace UR\Service\DataSource;

use DateTime;
use Doctrine\Common\Collections\Collection;
use UR\DomainManager\DataSourceIntegrationBackfillHistoryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Entity\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceInterface;

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

    /**
     * BackFillDataSource constructor.
     * @param DataSourceIntegrationBackfillHistoryManagerInterface $backFillHistoryManager
     * @param DataSourceManagerInterface $dataSourceManager
     */
    public function __construct(DataSourceIntegrationBackfillHistoryManagerInterface $backFillHistoryManager, DataSourceManagerInterface $dataSourceManager)
    {
        $this->backFillHistoryManager = $backFillHistoryManager;
        $this->dataSourceManager = $dataSourceManager;
    }

    /**
     * @inheritdoc
     */
    public function createBackfillForDataSource(DataSourceInterface $dataSource)
    {
        $dataSourceIntegrations = $dataSource->getDataSourceIntegrations();
        $dataSourceIntegrations = $dataSourceIntegrations instanceof Collection ? $dataSourceIntegrations->toArray() : $dataSourceIntegrations;
        if (!is_array($dataSourceIntegrations) || empty($dataSourceIntegrations)) {
            return '';
        }

        $dataSourceIntegration = end($dataSourceIntegrations);

        if ($dataSource->getBackfillMissingDateRunning() == true) {
            $this->backFillHistoryManager->deleteCurrentAutoCreateBackFillHistory($dataSource);
        }

        if (!is_array($dataSource->getMissingDate()) || empty($dataSource->getMissingDate())) {
            return '';
        }

        $missingDateRanges = $dataSource->getMissingDate();
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
}