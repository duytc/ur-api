<?php

namespace UR\Service\SynchronizeUser;

use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\Model\User\Role\PublisherInterface;

class SynchronizeUserService implements SynchronizeUserServiceInterface
{
    /** @var PublisherManagerInterface */
    private $publisherManager;
    /** @var EntityManagerInterface */
    private $em;

    public function __construct(EntityManagerInterface $em, PublisherManagerInterface $publisherManager)
    {
        $this->em = $em;
        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function synchronizeUser(array $entityData)
    {
        $id = $entityData['id'];
        /** @var PublisherInterface|UserInterface $existingPublisher */
        $existingPublisher = $this->publisherManager->findPublisher($id);
        if (!in_array(User::MODULE_UNIFIED_REPORT, $entityData['roles'])) {
            $entityData['enabled'] = false;
        }

        // support master account for a Publisher
        $masterAccountId = $this->getUserParams($entityData, 'masterAccount', null);
        $masterAccount = empty($masterAccountId) ? null : $this->publisherManager->find($masterAccountId);
        $masterAccount = ($masterAccount instanceof PublisherInterface) ? $masterAccount : null;
        $entityData['masterAccount'] = $masterAccount;

        if ($existingPublisher instanceof PublisherInterface) {
            $existingPublisher = $this->mapUserInfo($entityData, $existingPublisher);
            $this->publisherManager->save($existingPublisher);
        } else {
            /** @var PublisherInterface|UserInterface $newPublisher */
            $newPublisher = new User();
            $newPublisher = $this->mapUserInfo($entityData, $newPublisher);
            $this->publisherManager->save($newPublisher);

            $connection = $this->em->getConnection();
            $userId = $newPublisher->getId();
            $statement = $connection->prepare("SET FOREIGN_KEY_CHECKS = 0;"
                . "UPDATE core_user SET id = " . $id . " WHERE id = " . $userId
                . ";UPDATE core_user_publisher SET id = " . $id . " WHERE id = " . $userId
                . ";SET FOREIGN_KEY_CHECKS = 1");
            $statement->execute();
        }
    }

    /**
     * @param array $userDataFromTagcadeApi
     * @param PublisherInterface $userInUrAPI
     * @return PublisherInterface
     */
    private function mapUserInfo(array $userDataFromTagcadeApi, PublisherInterface $userInUrAPI)
    {
        $userInUrAPI->setBillingRate($this->getUserParams($userDataFromTagcadeApi, 'billingRate', null));
        $userInUrAPI->setFirstName($this->getUserParams($userDataFromTagcadeApi, 'firstName', null));
        $userInUrAPI->setLastName($this->getUserParams($userDataFromTagcadeApi, 'lastName', null));
        $userInUrAPI->setCompany($this->getUserParams($userDataFromTagcadeApi, 'company', null));
        $userInUrAPI->setPhone($this->getUserParams($userDataFromTagcadeApi, 'phone', null));
        $userInUrAPI->setCity($this->getUserParams($userDataFromTagcadeApi, 'city', null));
        $userInUrAPI->setState($this->getUserParams($userDataFromTagcadeApi, 'state', null));
        $userInUrAPI->setAddress($this->getUserParams($userDataFromTagcadeApi, 'address', null));
        $userInUrAPI->setPostalCode($this->getUserParams($userDataFromTagcadeApi, 'postalCode', null));
        $userInUrAPI->setCountry($this->getUserParams($userDataFromTagcadeApi, 'country', null));
        $userInUrAPI->setSettings($this->getUserParams($userDataFromTagcadeApi, 'settings', null));
        $userInUrAPI->setTagDomain($this->getUserParams($userDataFromTagcadeApi, 'tagDomain', null));
        $userInUrAPI->setExchanges($this->getUserParams($userDataFromTagcadeApi, 'exchanges', null));
        $userInUrAPI->setBidders($this->getUserParams($userDataFromTagcadeApi, 'bidders', null));
        $userInUrAPI->setEnabledModules($this->getUserParams($userDataFromTagcadeApi, 'enabledModules', null));
        $userInUrAPI->setUsername($this->getUserParams($userDataFromTagcadeApi, 'username', null));
        $userInUrAPI->setPassword($this->getUserParams($userDataFromTagcadeApi, 'password', null));
        $userInUrAPI->setEmail($this->getUserParams($userDataFromTagcadeApi, 'email', null));
        $userInUrAPI->setEnabled($this->getUserParams($userDataFromTagcadeApi, 'enabled', null));
        $userInUrAPI->setMasterAccount($this->getUserParams($userDataFromTagcadeApi, 'masterAccount', null));
        $userInUrAPI->setEmailSendAlert($this->getUserParams($userDataFromTagcadeApi, 'emailSendAlert', null));

        return $userInUrAPI;
    }

    /**
     * @param array $userEntity
     * @param $key
     * @param $default
     * @return mixed
     */
    private function getUserParams(array $userEntity, $key, $default)
    {
        return array_key_exists($key, $userEntity) ? $userEntity[$key] : $default;
    }
}