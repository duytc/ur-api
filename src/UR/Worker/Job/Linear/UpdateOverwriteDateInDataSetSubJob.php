<?php

namespace UR\Worker\Job\Linear;

use Pubvantage\Worker\Job\ExpirableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\DataSet\OverwriteDateUpdaterInterface;

class UpdateOverwriteDateInDataSetSubJob implements SubJobInterface, ExpirableJobInterface
{
    const JOB_NAME = 'updateOverwriteDateInDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';

    /** @var OverwriteDateUpdaterInterface  */
    private $overwriteDateUpdater;


    public function __construct(OverwriteDateUpdaterInterface $overwriteDateUpdater)
    {
        $this->overwriteDateUpdater = $overwriteDateUpdater;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = $params->getRequiredParam(self::DATA_SET_ID);
        $this->overwriteDateUpdater->updateOverwriteDate($dataSetId);
    }
}