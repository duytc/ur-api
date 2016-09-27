<?php

namespace Tagcade\Bundle\UserBundle\Tests\Controller;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TokenControllerTest extends WebTestCase
{
    public function setUp()
    {
        $classes = array(
            'Tagcade\Tests\Fixtures\LoadUserData',
        );
        $this->loadFixtures($classes);
    }

    public function testGetTokenAction()
    {
        $parameters = [
            'username' => 'pub',
            'password' => '12345'
        ];

        $response = $this->makeJWTRequest($parameters);

        $this->assertEquals(200, $response->getStatusCode());

        return $response;
    }

    public function testGetTokenWithInvalidCredentialsAction()
    {
        $parameters = [
            'username' => 'invalid',
            'password' => 'invalid'
        ];

        $response = $this->makeJWTRequest($parameters);

        $this->assertEquals(401, $response->getStatusCode());

        return $response;
    }

    /**
     * @depends testGetTokenAction
     * @param Response $response
     */
    public function testValidJWTResponse(Response $response)
    {
        $json = json_decode($response->getContent(), true);

        $this->assertTrue(json_last_error() == JSON_ERROR_NONE);

        $this->assertTrue(isset($json['token']));
    }

    protected function makeJWTRequest(array $parameters)
    {
        $client = static::createClient();
        $client->request('POST', $this->getUrl('api_get_token'), $parameters);

        return $client->getResponse();
    }
}