<?php

namespace UR\Service\SynchronizeUser;

use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\Model\User\UserEntityInterface;

class SynchronizeUserService implements SynchronizeUserServiceInterface
{
    private $publisherManager;

    public function __construct(PublisherManagerInterface $publisherManager)
    {
        $this->publisherManager = $publisherManager;
    }

    public function synchronizeUser($entity)
    {
        $id = $entity['id'];
        $publisher = $this->publisherManager->findPublisher($id);
        if (!in_array(User::MODULE_UNIFIED_REPORT, $entity['roles'])) {
            $entity['enabled'] = false;
        }

        if ($publisher instanceof UserEntityInterface) {
//            $publisher->setBillingRate($entity['billingRate']);
//            $publisher->setFirstName($entity['firstName']);
//            $publisher->setLastName($entity['lastName']);
            $publisher->setCompany($entity['company']);
//            $publisher->setPhone($entity['phone']);
//            $publisher->setCity($entity['city']);
//            $publisher->setState($entity['state']);
//            $publisher->setAddress($entity['address']);
//            $publisher->setPostalCode($entity['postalCode']);
//            $publisher->setCountry($entity['country']);
//            $publisher->setSettings($entity['settings']);
//            $publisher->setTagDomain($entity['tagDomain']);
//            $publisher->setExchanges($entity['exchanges']);
//            $publisher->setBidders($entity['bidders']);
//            $publisher->setEnabledModules($entity['enabledModules']);
            $publisher->setUsername($entity['username']);
            $publisher->setPassword($entity['password']);
            $publisher->setEmail($entity['email']);
            $publisher->setEnabled($entity['enabled']);

            $this->publisherManager->save($publisher);
        } else {
            $user = new User();
//            $user->setBillingRate($entity['billingRate']);
//            $user->setFirstName($entity['firstName']);
//            $user->setLastName($entity['lastName']);
            $user->setCompany($entity['company']);
//            $user->setPhone($entity['phone']);
//            $user->setCity($entity['city']);
//            $user->setState($entity['state']);
//            $user->setAddress($entity['address']);
//            $user->setPostalCode($entity['postalCode']);
//            $user->setCountry($entity['country']);
//            $user->setSettings($entity['settings']);
//            $user->setTagDomain($entity['tagDomain']);
//            $user->setExchanges($entity['exchanges']);
//            $user->setBidders($entity['bidders']);
//            $user->setEnabledModules($entity['enabledModules']);
            $user->setUsername($entity['username']);
            $user->setPassword($entity['password']);
            $user->setEmail($entity['email']);
            $user->setEnabled($entity['enabled']);
            $user->setId($id);
            $this->publisherManager->save($user);
        }
    }
}