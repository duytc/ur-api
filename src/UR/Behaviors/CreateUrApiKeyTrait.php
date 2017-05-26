<?php

namespace UR\Behaviors;


trait CreateUrApiKeyTrait
{
    public function generateUrApiKey($userName)
    {
        $userName = $this->normalizeText($userName);
        $tokenString = bin2hex(random_bytes(18));
        $apiKey = $userName . '.' . $tokenString;
        return $apiKey;
    }

    /**
     * @param $text
     * @return string
     */
    public function normalizeText($text) {
        $text = str_replace(' ', '', $text);
        $text = preg_replace('/[^A-Za-z0-9\-]/', '', $text);
        return strtolower($text);
    }
}