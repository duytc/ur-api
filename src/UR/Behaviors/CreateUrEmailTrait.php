<?php

namespace UR\Behaviors;


trait CreateUrEmailTrait
{
    public function generateUniqueUrEmail($publisherId, $urEmailTemplate)
    {
        $urEmail = str_replace('$PUBLISHER_ID$', $publisherId, $urEmailTemplate);
        $tokenString = bin2hex(random_bytes(18));
        $urEmail = str_replace('$TOKEN$', $tokenString, $urEmail);

        return $urEmail;
    }
}