<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class JWTCreatedListener
{
    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['id'] = $user->getId()?->toRfc4122();
        $payload['email'] = $user->getEmail();
        $payload['fullName'] = $user->getFullName();
        $payload['roles'] = $user->getRoles();

        $garage = $user->getGarage();
        $payload['garageId'] = $garage?->getId()?->toRfc4122();
        $payload['garageName'] = $garage?->getName();

        $event->setData($payload);
    }
}
