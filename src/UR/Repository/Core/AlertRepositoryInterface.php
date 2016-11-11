<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;

interface AlertRepositoryInterface extends ObjectRepository
{
    public function deleteAlertsByIds($ids);

    public function updateMarkAsReadByIds($ids);

    public function updateMarkAsUnreadByIds($ids);
}