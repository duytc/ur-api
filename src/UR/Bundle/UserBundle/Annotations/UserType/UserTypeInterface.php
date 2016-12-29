<?php

namespace UR\Bundle\UserBundle\Annotations\UserType;

interface UserTypeInterface
{
    /**
     * @return string
     */
    public function getUserClass();
}