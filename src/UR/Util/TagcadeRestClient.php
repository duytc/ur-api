<?php

namespace UR\Util;

use Exception;
use RestClient\CurlRestClient;
use UR\Service\OptimizationRule\AutomatedOptimization\Pubvantage\PubvantageOptimizer;
use UR\Service\OptimizationRule\AutomatedOptimization\PubvantageVideo\PubvantageVideoOptimizer;
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
     * @param bool $force
     * @return mixed|null|string
     * @throws Exception
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
            throw new Exception('json decoding for token error');
        }

        if (!array_key_exists('token', $token)) {
            throw new Exception(sprintf('Could not authenticate user %s', $this->username));
        }

        $this->token = $token['token'];

        return $this->token;
    }

    /**
     * @param array $data
     * @param string $platformIntegration
     * @return mixed
     * @throws Exception
     */
    public function updateCacheForAdSlots(array $data, $platformIntegration = PubvantageOptimizer::PLATFORM_INTEGRATION)
    {
        $data['platform_integration'] = $platformIntegration;

        return $this->updateCacheForPubvantage($data);
    }

    /**
     * @param array $data
     * @param string $platformIntegration
     * @return mixed
     * @throws Exception
     */
    public function updateCacheForWaterFallTags(array $data, $platformIntegration = PubvantageVideoOptimizer::PLATFORM_INTEGRATION)
    {
        $data['platform_integration'] = $platformIntegration;

        return $this->updateCacheForPubvantage($data);
    }

    /**
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    private function updateCacheForPubvantage(array $data)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
        $header = array('Authorization: Bearer ' . $this->getToken());

        $data = [
            'data' => json_encode($data)
        ];

        $result = $this->curl->executeQuery(
            $this->updateCacheUrl, // . '?XDEBUG_SESSION_START=1',
            'POST',
            $header,
            $data
        );

        $this->curl->close();

        /* decode and parse */
        $result = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid response (json decode failed)');
        }

        if (is_array($result) && array_key_exists('code', $result) && !in_array($result['code'], [200, 201, 204])) {
            $messageDetail = array_key_exists('message', $result) ? $result['message'] : 'unknown';
            throw new Exception(sprintf('Failure to update 3rd party integrations (update cache). Detail: %s', $messageDetail));
        }

        return $result;
    }

    /**
     * @param array $data
     * @param string $platformIntegration
     * @return mixed
     * @throws Exception
     */
    public function testCacheForAdSlots(array $data, $platformIntegration = PubvantageOptimizer::PLATFORM_INTEGRATION)
    {
        $data['platform_integration'] = $platformIntegration;

        return $this->testCacheForPubvantage($data);
    }

    /**
     * @param array $data
     * @param string $platformIntegration
     * @return mixed
     * @throws Exception
     */
    public function testCacheForWaterFallTags(array $data, $platformIntegration = PubvantageVideoOptimizer::PLATFORM_INTEGRATION)
    {
        $data['platform_integration'] = $platformIntegration;

        return $this->testCacheForPubvantage($data);
    }

    /**
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    private function testCacheForPubvantage(array $data)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
        $header = array('Authorization: Bearer ' . $this->getToken());

        $data = [
            'data' => json_encode($data)
        ];

        $result = $this->curl->executeQuery(
            $this->testCacheUrl, // . '?XDEBUG_SESSION_START=1',
            'POST',
            $header,
            $data
        );

        $this->curl->close();

        /* decode and parse */
        $result = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid response (json decode failed)');
        }

        if (!is_array($result)) {
            throw new Exception('Failure to update 3rd party integrations (get previous positions). Detail: unknown');
        }

        if (array_key_exists('code', $result) && !in_array($result['code'], [200, 201, 204])) {
            $messageDetail = array_key_exists('message', $result) ? $result['message'] : 'unknown';
            throw new Exception(sprintf('Failure to update 3rd party integrations (get previous position). Detail: %s', $messageDetail));
        }

        return $result;
    }

    /**
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public function getScoresForOptimizationRule(array $data)
    {
        /* important: not try-catch here, we need let getToken() throw exception when authentication failed */
        /*  $scores = $this->curl->executeQuery(
              $this->scoreFetcherUrl,
              'POST',
              ["Content-Type" => "application/json;charset=UTF-8"],
              $data

              $this->curl->close();
          );*/

        $scores = $this->callRestAPI('POST', $this->scoreFetcherUrl, json_encode($data));
        $scores = json_decode($scores, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('json decoding error when get scores');
        }

        if (array_key_exists('code', $scores) && !in_array($scores['code'], [200, 201, 204])) {
            $messageDetail = array_key_exists('message', $scores) ? $scores['message'] : 'unknown';
            throw new Exception(sprintf('failed to get scores, code %d. Detail: %s', $scores['code'], $messageDetail));
        }

        return $scores;
    }
}