<?php

namespace UR\Behaviors;


trait CreateUrApiKeyTrait
{
    public function generateUrApiKey($userName)
    {
        $tokenString = bin2hex(random_bytes(18));
        $apiKey = $userName . '.' . $tokenString;
        return $apiKey;
    }
}