<?php

namespace UR\Util;

use RestClient\CurlRestClient;
use UR\Service\RestClientTrait;

class TagcadeRestClient
{
    use RestClientTrait;
    const VIA_MODULE_EMAIL_WEB_HOOK = 1;
    const VIA_MODULE_FETCHER = 2;

    /** @var null */
    private $username;

    /** @var array */
    private $password;

    /** @var CurlRestClient */
    private $curl;

    private $getTokenUrl;
    private $updateCacheUrl;
    private $testCacheUrl;
    private $scoreFetcherUrl;

    /**
     * store last token to save requests to api
     * @var string|null
     */
    private $token = null;

    const DEBUG = 0;

    function __construct($username, $password, $getTokenUrl, $updateCacheUrl, $scoreFetcherUrl, $testCacheUrl)
    {
        $this->username = $username;
        $this->password = $password;
        $this->curl = new CurlRestClient();
        $this->getTokenUrl = $getTokenUrl;
        $this->updateCacheUrl = $updateCacheUrl;
        $this->scoreFetcherUrl = $scoreFetcherUrl;
        $this->testCacheUrl = $testCacheUrl;
    }

    /**
     * @inheritdoc
     */
    public function getToken($force = false)
    {
        if ($this->token != null && $force == false) {
            return $this->token;
        }

        $data = array('username' => $this->username, 'password' => $this->password);
        $token = $this->curl->executeQuery($this->getTokenUrl, 'POST', array(), $data);
        $this->curl->close();
        $token = json_decode($token, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('json decoding for token error');
        }

        if (!array_key_exists('token', $token)) {
            throw new \Exception(sprintf('Could not authenticate user %s', $this->username));
        }

        $this->token = $token['token'];

        return $this->token;
    }

    /**
     * @inheritdoc
     */
    public function updateCacheForAdSlots($data)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
        $header = array('Authorization: Bearer ' . $this->getToken());

        $data = [
            'data' => json_encode($data)
        ];

        $result = $this->curl->executeQuery(
            $this->updateCacheUrl .'?XDEBUG_SESSION_START=1',
            'POST',
            $header,
            $data
        );

        $this->curl->close();

        /* decode and parse */
        $result = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid response (json decode failed)');
        }

        if (is_array($result) && array_key_exists('code', $result) && $result['code'] != 200) {
            throw new \Exception('Failure to update 3rd party integrations (update cache)');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function testCacheForAdSlots($data)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
        $header = array('Authorization: Bearer ' . $this->getToken());

        $data = [
            'data' => json_encode($data)
        ];

        $result = $this->curl->executeQuery(
            $this->testCacheUrl .'?XDEBUG_SESSION_START=1',
            'POST',
            $header,
            $data
        );

        $this->curl->close();

        /* decode and parse */
        $result = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid response (json decode failed)');
        }

        if (is_array($result) && array_key_exists('code', $result) && $result['code'] != 200) {
            throw new \Exception('Failure to update 3rd party integrations (get previous adTags position)');
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getScoresForOptimizationRule($data)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
      /*  $scores = $this->curl->executeQuery(
            $this->scoreFetcherUrl,
            'POST',
            ["Content-Type" => "application/json;charset=UTF-8"],
            $data

            $this->curl->close();
        );*/

        $scores= $this->callRestAPI('POST', $this->scoreFetcherUrl, json_encode($data));
        $scores = json_decode($scores, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('json decoding error when get scores');
        }

        if (array_key_exists('code', $scores) && $scores['code'] != 200) {
            throw new \Exception(sprintf('failed to get scores, code %d', $scores['code']));
        }

        return $scores;
    }

    /**
     * @inheritdoc
     */
    public function getAdTagsFromAdSlot($adSlotId)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
        $header = array('Authorization: Bearer ' . $this->getToken());
        $url = str_replace("{id}", $adSlotId, $this->getAdSlotUrl);

        $adTags = $this->curl->executeQuery(
            $url,
            'GET',
            $header,
            []
        );

        $this->curl->close();

        $adTags = json_decode($adTags, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('json decoding error when get scores');
        }

        if (array_key_exists('code', $adTags) && $adTags['code'] != 200) {
            throw new \Exception(sprintf('failed to get adtags, code %d', $adTags['code']));
        }

        return $adTags;
    }

    /**
     * check if file too large (http code 413)
     *
     * @param string $postResult html response
     * @return bool|int
     */
    private function checkIfHttp413($postResult)
    {
        /*
         * <html>
         * <head><title>413 Request Entity Too Large</title></head>
         * <body bgcolor="white">
         * <center><h1>413 Request Entity Too Large</h1></center>
         * <hr><center>nginx/1.10.2</center>
         * </body>
         * </html>
         */
        if (empty($postResult) || !is_string($postResult)) {
            return false;
        }

        return false !== strpos($postResult, '<head><title>413');
    }
}