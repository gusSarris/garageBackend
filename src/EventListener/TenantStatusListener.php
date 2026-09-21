<?php

namespace App\EventListener;

use App\Entity\Garage;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class TenantStatusListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
    public function onRequestEvent(RequestEvent $event): void
    {
        // 1. Only process main requests
        if (!$event->isMainRequest()) {
            return;
        }

        // 2. Only process tenant routes (^/api/garage)
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/garage')) {
            return;
        }

        // 3. Resolve authenticated user
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // 4. Super Admins bypass
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        // 5. Evaluate tenant status
        $garage = $user->getGarage();
        if (!$garage instanceof Garage) {
            $event->setResponse(new JsonResponse(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST));
            return;
        }

        $isValidSubscription = in_array($garage->getSubscriptionStatus(), ['trial', 'active'], true);
        $isActive = (bool) $garage->isActive();

        if (!$isActive || !$isValidSubscription) {
            $event->setResponse(new JsonResponse([
                'error' => 'Workshop is inactive or subscription has expired.',
                'code' => 'WORKSHOP_INACTIVE_OR_EXPIRED',
                'isActive' => $isActive,
                'subscriptionStatus' => $garage->getSubscriptionStatus(),
            ], Response::HTTP_FORBIDDEN));
        }
    }
}
