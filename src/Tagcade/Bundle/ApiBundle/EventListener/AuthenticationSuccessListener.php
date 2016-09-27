<?php

namespace Tagcade\Bundle\ApiBundle\EventListener;

use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tagcade\Bundle\ApiBundle\Service\JWTResponseTransformer;

class AuthenticationSuccessListener
{
    protected $jwtResponseTransformer;
    protected $userManager;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(JWTResponseTransformer $jwtResponseTransformer, UserManagerInterface $userManager)
    {
        $this->jwtResponseTransformer = $jwtResponseTransformer;
        $this->userManager = $userManager;
    }

    /**
     * @param AuthenticationSuccessEvent $event
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data = $this->jwtResponseTransformer->transform($data, $user);

        $event->setData($data);

        $user->setLastLogin(new \DateTime());
        $this->userManager->updateUser($user);
    }
}