<?php

namespace UR\Service\SynchronizeUser;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\Model\User\Role\PublisherInterface;

class SynchronizeUserService implements SynchronizeUserServiceInterface
{
    const SALT = 'salt';

    /** @var PublisherManagerInterface */
    private $publisherManager;

    /** @var Connection  */
    private $connection;

    public function __construct(EntityManagerInterface $em, PublisherManagerInterface $publisherManager)
    {
        $this->connection = $em->getConnection();
        $this->publisherManager = $publisherManager;
    }

    /**
     * @inheritdoc
     */
    public function synchronizeUser(array $entityData)
    {
        $idFromTagcadeAPI = $entityData['id'];
        $userName = $this->getUserParams($entityData, 'username', null);
        $salt = array_key_exists(self::SALT, $entityData) ? $entityData[self::SALT] : null;
        $entityData = $this->addMasterAccountInfo($entityData);

        /** @var PublisherInterface|UserInterface $publisher */
        $publisher = $this->publisherManager->findUserByUsernameOrEmail($userName);
        if (!$publisher instanceof PublisherInterface) {
            $publisher = new User();
        }

        $publisher = $this->updateUserInfo($entityData, $publisher);
        $this->updateIdForPublisher($idFromTagcadeAPI, $publisher->getId());
        $this->updateSaltForPublisher($salt, $idFromTagcadeAPI, $publisher->getSalt());
    }

    /**
     * @param array $userData
     * @param PublisherInterface|UserInterface $publisher
     * @return PublisherInterface
     */
    private function updateUserInfo(array $userData, PublisherInterface $publisher)
    {
        $publisher->setBillingRate($this->getUserParams($userData, 'billingRate', null));
        $publisher->setFirstName($this->getUserParams($userData, 'firstName', null));
        $publisher->setLastName($this->getUserParams($userData, 'lastName', null));
        $publisher->setCompany($this->getUserParams($userData, 'company', null));
        $publisher->setPhone($this->getUserParams($userData, 'phone', null));
        $publisher->setCity($this->getUserParams($userData, 'city', null));
        $publisher->setState($this->getUserParams($userData, 'state', null));
        $publisher->setAddress($this->getUserParams($userData, 'address', null));
        $publisher->setPostalCode($this->getUserParams($userData, 'postalCode', null));
        $publisher->setCountry($this->getUserParams($userData, 'country', null));
        $publisher->setSettings($this->getUserParams($userData, 'settings', null));
        $publisher->setTagDomain($this->getUserParams($userData, 'tagDomain', null));
        $publisher->setExchanges($this->getUserParams($userData, 'exchanges', null));
        $publisher->setBidders($this->getUserParams($userData, 'bidders', null));
        $publisher->setEnabledModules($this->getUserParams($userData, 'enabledModules', null));
        $publisher->setUsername($this->getUserParams($userData, 'username', null));
        $publisher->setPassword($this->getUserParams($userData, 'password', null));
        $publisher->setEmail($this->getUserParams($userData, 'email', null));
        $publisher->setEnabled($this->getUserParams($userData, 'enabled', null));
        $publisher->setMasterAccount($this->getUserParams($userData, 'masterAccount', null));
        $publisher->setEmailSendAlert($this->getUserParams($userData, 'emailSendAlert', null));

        $this->publisherManager->save($publisher);

        return $publisher;
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

    /**
     * Build raw SQL to update salt because FOS\UserBundle\Model\User in Symfony does not support method to update this
     * @param $salt
     * @param $publisherId
     * @param $currentSalt
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updateSaltForPublisher($salt, $publisherId, $currentSalt = null)
    {
        if ($salt == $currentSalt) {
            return;
        }

        $statementForSalt = $this->connection->prepare(sprintf('UPDATE core_user SET salt = "%s" WHERE id = %s', $salt, $publisherId));
        try {
            $statementForSalt->execute();
        } catch (\Exception $e) {

        }
    }

    /**
     * @param $correctId
     * @param $wrongId
     */
    private function updateIdForPublisher($correctId, $wrongId)
    {
        if ($correctId == $wrongId) {
            return;
        }

        $updateSQLs[] = sprintf('SET FOREIGN_KEY_CHECKS = 0');
        $updateSQLs[] = sprintf("UPDATE core_user SET id = %s WHERE id = %s", $correctId, $wrongId);
        $updateSQLs[] = sprintf("UPDATE core_user_publisher SET id = %s WHERE id = %s", $correctId, $wrongId);
        $updateSQLs[] = sprintf('SET FOREIGN_KEY_CHECKS = 1');

        $sql = implode(";", $updateSQLs);
        try {
            $this->connection->exec($sql);
        } catch (\Exception $e) {
        }
    }

    /**
     * @param $entityData
     * @return mixed
     */
    private function addMasterAccountInfo($entityData)
    {
        if (!in_array(User::MODULE_UNIFIED_REPORT, $entityData['roles'])) {
            $entityData['enabled'] = false;
        }

        // support master account for a Publisher
        $masterAccountId = $this->getUserParams($entityData, 'masterAccount', null);
        $masterAccount = empty($masterAccountId) ? null : $this->publisherManager->find($masterAccountId);
        $masterAccount = ($masterAccount instanceof PublisherInterface) ? $masterAccount : null;
        $entityData['masterAccount'] = $masterAccount;

        return $entityData;
    }
}