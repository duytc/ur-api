<?php

namespace UR\Bundle\ApiBundle\Service;


use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SessionRequestProcessor
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function processRecord(array $record)
    {
        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            return $record;
        }

        /**
         *
         * @var \UR\Model\User\Role\UserRoleInterface $user
         */
        $user = $token->getUser();

        $record['extra']['user'] = $user->getId();

        return $record;
    }
} 