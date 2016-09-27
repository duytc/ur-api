<?php

namespace Tagcade\Bundle\UserBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Tagcade\Exception\InvalidArgumentException;
use Tagcade\Model\User\UserEntityInterface;

abstract class User extends BaseUser implements UserEntityInterface
{
    const USER_ROLE_PREFIX = 'ROLE_';
    const MODULE_PREFIX = 'MODULE_';

    const MODULE_DISPLAY = 'MODULE_DISPLAY';
    const MODULE_VIDEO = 'MODULE_VIDEO';
    const MODULE_ANALYTICS = 'MODULE_ANALYTICS'; //source
    const MODULE_VIDEO_ANALYTICS = 'MODULE_VIDEO_ANALYTICS'; //source
    const MODULE_FRAUD_DETECTION = 'MODULE_FRAUD_DETECTION';
    const MODULE_UNIFIED_REPORT = 'MODULE_UNIFIED_REPORT';
    const MODULE_RTB = 'MODULE_RTB';
    const MODULE_HEADER_BIDDING = 'MODULE_HEADER_BIDDING';

    // we have to redefine the properties we wish to expose with JMS Serializer Bundle

    protected $id;
    protected $username;
    protected $email;
    protected $enabled;
    protected $lastLogin;
    protected $roles;
    protected $joinDate;

    protected $type;
    protected $testAccount = false;

    /**
     * @inheritdoc
     */
    public function hasDisplayModule()
    {
        return in_array(static::MODULE_DISPLAY, $this->getEnabledModules());
    }

    /**
     * @inheritdoc
     */
    public function hasAnalyticsModule()
    {
        return in_array(static::MODULE_ANALYTICS, $this->getEnabledModules());
    }

    /**
     * @return bool
     */
    public function hasVideoAnalyticsModule()
    {
        return in_array(static::MODULE_VIDEO_ANALYTICS, $this->getEnabledModules());
    }

    /**
     * @return bool
     */
    public function hasVideoModule()
    {
        return in_array(static::MODULE_VIDEO, $this->getEnabledModules());
    }

    /**
     * @return bool
     */
    public function hasUnifiedReportModule()
    {
        return in_array(static::MODULE_UNIFIED_REPORT, $this->getEnabledModules());
    }

    public function hasRtbModule()
    {
        return in_array(static::MODULE_RTB, $this->getEnabledModules());
    }

    /**
     * @return bool
     */
    public function hasHeaderBiddingModule()
    {
        return in_array(static::MODULE_HEADER_BIDDING, $this->getEnabledModules());
    }

    /**
     * @inheritdoc
     */
    public function setEnabledModules(array $modules)
    {
        $this->replaceRoles(
            $this->getEnabledModules(), // old roles
            $modules, // new roles
            static::MODULE_PREFIX,
            $strict = false // this means we add the role prefix and convert to uppercase if it does not exist
        );
    }

    /**
     * @inheritdoc
     */
    public function setUserRoles(array $roles)
    {
        $this->replaceRoles(
            $this->getUserRoles(), // old roles
            $roles, // new roles
            static::USER_ROLE_PREFIX,
            $strict = false // this means we add the role prefix and convert to uppercase if it does not exist
        );
    }

    /**
     * @inheritdoc
     */
    public function getEnabledModules()
    {
        return $this->getRolesWithPrefix(static::MODULE_PREFIX);
    }

    /**
     * @inheritdoc
     */
    public function getUserRoles()
    {
        $roles = $this->getRolesWithPrefix(static::USER_ROLE_PREFIX);

        $roles = array_filter($roles, function ($role) {
            return $role !== static::ROLE_DEFAULT;
        });

        return $roles;
    }

    public function setEmail($email)
    {
        if (empty($email)) {
            $email = null;
        }

        $this->email = $email;

        return $this;
    }

    public function setEmailCanonical($emailCanonical)
    {
        if (empty($emailCanonical)) {
            $emailCanonical = null;
        }

        $this->emailCanonical = $emailCanonical;

        return $this;
    }

    /**
     * @param string $prefix i.e ROLE_ or FEATURE_
     * @return array
     */
    protected function getRolesWithPrefix($prefix)
    {
        $roles = array_filter($this->getRoles(), function ($role) use ($prefix) {
            return $this->checkRoleHasPrefix($role, $prefix);
        });

        return array_values($roles);
    }

    protected function checkRoleHasPrefix($role, $prefix)
    {
        return strpos($role, $prefix) === 0;
    }

    protected function addRoles(array $roles)
    {
        foreach ($roles as $role) {
            $this->addRole($role);
        }
    }

    protected function removeRoles(array $roles)
    {
        foreach ($roles as $role) {
            $this->removeRole($role);
        }
    }

    /**
     * @return boolean
     */
    public function isTestAccount()
    {
        return $this->testAccount;
    }

    /**
     * @param boolean $testAccount
     * @return $this|\Tagcade\Model\User\UserEntityInterface
     */
    public function setTestAccount($testAccount)
    {
        $this->testAccount = $testAccount;

        return $this;
    }

    /**
     * @param array $oldRoles
     * @param array $newRoles
     * @param $requiredRolePrefix
     * @param bool $strict ensure that the roles have the prefix, don't try to add it
     */
    protected function replaceRoles(array $oldRoles, array $newRoles, $requiredRolePrefix, $strict = false)
    {
        $this->removeRoles($oldRoles);

        foreach ($newRoles as $role) {
            // converts fraud_detection to FEATURE_FRAUD_DETECTION
            if (!$this->checkRoleHasPrefix($role, $requiredRolePrefix)) {
                if ($strict) {
                    throw new InvalidArgumentException("role '%s' does not have the required prefix '%s'", $role, $requiredRolePrefix);
                }

                $role = $requiredRolePrefix . strtoupper($role);
            }

            $this->addRole($role);
        }
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
}