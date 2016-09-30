<?php

namespace UR\Bundle\ApiBundle\Service;

use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class JWTResponseTransformer
{
    public function transform(array $data, UserEntityInterface $user)
    {
        $data['id'] = $user->getId();
        $data['username'] = $user->getUsername();
        $data['userRoles'] = $user->getUserRoles();
        $data['enabledModules'] = $user->getEnabledModules();

        if ($user instanceof PublisherInterface) {
            $data['settings'] = $user->getSettings();
            $data['exchanges'] = $user->getExchanges();
        }

        return $data;
    }
}