<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
final class AuthController extends AbstractController
{
    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $garage = $user->getGarage();
        $fullName = (string) $user->getFullName();
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        return $this->json([
            'id' => $user->getId()?->toRfc4122(),
            'email' => $user->getEmail(),
            'fullName' => $fullName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'roles' => $user->getRoles(),
            'garage' => $garage ? [
                'id' => $garage->getId()?->toRfc4122(),
                'name' => $garage->getName(),
                'subscriptionStatus' => $garage->getSubscriptionStatus(),
            ] : null,
        ]);
    }
}
