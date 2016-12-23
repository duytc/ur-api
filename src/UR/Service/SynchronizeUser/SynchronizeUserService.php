<?php

namespace UR\Service\SynchronizeUser;

use Doctrine\ORM\EntityManagerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\Model\User\UserEntityInterface;

class SynchronizeUserService implements SynchronizeUserServiceInterface
{
    private $publisherManager;
    private $em;

    public function __construct(EntityManagerInterface $em, PublisherManagerInterface $publisherManager)
    {
        $this->em = $em;
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
            $publisher->setBillingRate($this->getUserParams($entity,'billingRate', null));
            $publisher->setFirstName($this->getUserParams($entity,'firstName', null));
            $publisher->setLastName($this->getUserParams($entity,'lastName', null));
            $publisher->setCompany($this->getUserParams($entity,'company', null));
            $publisher->setPhone($this->getUserParams($entity,'phone', null));
            $publisher->setCity($this->getUserParams($entity,'city', null));
            $publisher->setState($this->getUserParams($entity,'state', null));
            $publisher->setAddress($this->getUserParams($entity,'address', null));
            $publisher->setPostalCode($this->getUserParams($entity,'postalCode', null));
            $publisher->setCountry($this->getUserParams($entity,'country', null));
            $publisher->setSettings($this->getUserParams($entity,'settings', null));
            $publisher->setTagDomain($this->getUserParams($entity,'tagDomain', null));
            $publisher->setExchanges($this->getUserParams($entity,'exchanges', null));
            $publisher->setBidders($this->getUserParams($entity,'bidders', null));
            $publisher->setEnabledModules($this->getUserParams($entity,'enabledModules', null));
            $publisher->setUsername($this->getUserParams($entity,'username', null));
            $publisher->setPassword($this->getUserParams($entity,'password', null));
            $publisher->setEmail($this->getUserParams($entity,'email', null));
            $publisher->setEnabled($this->getUserParams($entity,'enabled', null));

            $this->publisherManager->save($publisher);
        } else {
            $user = new User();
            $user->setBillingRate($this->getUserParams($entity,'billingRate', null));
            $user->setFirstName($this->getUserParams($entity,'firstName', null));
            $user->setLastName($this->getUserParams($entity,'lastName', null));
            $user->setCompany($this->getUserParams($entity,'company', null));
            $user->setPhone($this->getUserParams($entity,'phone', null));
            $user->setCity($this->getUserParams($entity,'city', null));
            $user->setState($this->getUserParams($entity,'state', null));
            $user->setAddress($this->getUserParams($entity,'address', null));
            $user->setPostalCode($this->getUserParams($entity,'postalCode', null));
            $user->setCountry($this->getUserParams($entity,'country', null));
            $user->setSettings($this->getUserParams($entity,'settings', null));
            $user->setTagDomain($this->getUserParams($entity,'tagDomain', null));
            $user->setExchanges($this->getUserParams($entity,'exchanges', null));
            $user->setBidders($this->getUserParams($entity,'bidders', null));
            $user->setEnabledModules($this->getUserParams($entity,'enabledModules', null));
            $user->setUsername($this->getUserParams($entity,'username', null));
            $user->setPassword($this->getUserParams($entity,'password', null));
            $user->setEmail($this->getUserParams($entity,'email', null));
            $user->setEnabled($this->getUserParams($entity,'enabled', null));

            $this->publisherManager->save($user);

            $connection = $this->em->getConnection();
            $userId = $user->getId();
            $statement = $connection->prepare("SET FOREIGN_KEY_CHECKS = 0;"
                . "UPDATE core_user SET id = " . $id . " WHERE id = " . $userId
                . ";UPDATE core_user_publisher SET id = " . $id . " WHERE id = " . $userId
                . ";SET FOREIGN_KEY_CHECKS = 1" );
            $statement->execute();
        }
    }

    private function getUserParams($userEntity, $key, $default){
        return array_key_exists($key, $userEntity) ? $userEntity[$key] : $default;
    }
}