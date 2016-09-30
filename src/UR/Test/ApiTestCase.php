<?php

namespace UR\Test;

use Symfony\Bundle\FrameworkBundle\Client;
use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiTestCase extends WebTestCase
{
    protected $fixtureExecutor;

    protected function getFixtureReference($ref)
    {
        if ($this->fixtureExecutor instanceof AbstractExecutor) {
            $repo = $this->fixtureExecutor->getReferenceRepository();

            if ($repo->hasReference($ref)) {
                return $repo->getReference($ref);
            }

            throw new \Exception('that fixture reference does not exist');
        }

        throw new \Exception('executor is not set, did you call loadFixtures?');
    }

    protected function getClient($accepts = 'application/json')
    {
        $client = static::createClient();

        if ($accepts) {
            $client->setServerParameter('HTTP_Accept', $accepts);
        }

        return $client;
    }

    protected function getClientForUser($user = null, $client = null)
    {
        if (!$client) {
            $client = $this->getClient();
        }

        if ($user) {
            $jwt = $this->getJWT($user);
            $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $jwt));
        }

        return $client;
    }

    /**
     * @param $username
     * @return \Namshi\JOSE\JWS;
     */
    protected function getJWT($username)
    {
        $user = $this->getMock(UserInterface::class);

        $user->expects($this->any())
            ->method('getUsername')
            ->will($this->returnValue($username));

        return $this->getContainer()->get('lexik_jwt_authentication.jwt_manager')->create($user);
    }

    /**
     * @param array $payload
     * @param string $route The symfony route name
     * @param string $method the HTTP method
     * @param Client|null $client
     * @return Response
     */
    protected function makeJsonRequest(array $payload, $route, $method = 'POST', Client $client = null)
    {
        if (null === $client) {
            $client = $this->getClient();
        }

        $client->request(
            $method,
            $this->getUrl($route),
            array(),
            array(),
            array('CONTENT_TYPE' => 'application/json'),
            json_encode($payload)
        );

        return $client->getResponse();
    }

    protected function assertJsonResponse(
        Response $response,
        $statusCode = 200,
        $checkValidJson = true,
        $contentType = 'application/json'
    )
    {
        $this->assertEquals(
            $statusCode, $response->getStatusCode(),
            $response->getContent()
        );
        $this->assertTrue(
            $response->headers->contains('Content-Type', $contentType),
            $response->headers
        );

        if ($checkValidJson) {
            $decode = json_decode($response->getContent());
            $this->assertTrue(($decode != null && $decode != false),
                'is response valid json: [' . $response->getContent() . ']'
            );
        }
    }
}