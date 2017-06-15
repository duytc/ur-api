<?php

namespace UR\Worker\Job\Concurrent;

use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\SynchronizeUser\SynchronizeUserServiceInterface;

class SynchronizeUser implements JobInterface
{
    const JOB_NAME = 'synchronizeUser';

    const PARAM_KEY_ENTITY = 'entity';

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @var SynchronizeUserServiceInterface
     */
    private $synchronizeUser;

    public function __construct(SynchronizeUserServiceInterface $synchronizeUser)
    {
        $this->synchronizeUser = $synchronizeUser;
    }

    public function run(JobParams $params)
    {
        $entity = (array) $params->getRequiredParam(self::PARAM_KEY_ENTITY);
        $this->synchronizeUser->synchronizeUser($entity);
    }
}