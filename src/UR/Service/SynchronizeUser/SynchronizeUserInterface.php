<?php


use UR\Model\User\Role\PublisherInterface;

interface SynchronizeUserInterface
{
    public function updateUser(PublisherInterface $publisher);
}