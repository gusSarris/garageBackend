<?php

namespace App\Controller\Api;

use App\DTO\Auth\ChangePasswordRequest;
use App\DTO\Auth\UpdateProfileRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->formatUserProfile($user), Response::HTTP_OK);
    }

    #[Route('/auth/me', name: 'auth_update_me', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updateMe(
        #[MapRequestPayload] UpdateProfileRequest $dto,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if ($dto->fullName !== null) {
            $user->setFullName($dto->fullName);
        }

        if ($dto->email !== null) {
            $targetEmail = strtolower(trim($dto->email));
            if ($targetEmail !== strtolower((string) $user->getEmail())) {
                $existingUser = $this->userRepository->findOneBy(['email' => $targetEmail]);
                if ($existingUser !== null && $existingUser->getId()?->toRfc4122() !== $user->getId()?->toRfc4122()) {
                    return $this->json(
                        ['error' => sprintf('User with email "%s" already exists.', $targetEmail)],
                        Response::HTTP_CONFLICT
                    );
                }
                $user->setEmail($targetEmail);
            }
        }

        $user->setUpdatedAt(new \DateTimeImmutable());

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(
                ['error' => sprintf('User with email "%s" already exists.', $dto->email)],
                Response::HTTP_CONFLICT
            );
        }

        return $this->json($this->formatUserProfile($user), Response::HTTP_OK);
    }

    #[Route('/auth/change-password', name: 'auth_change_password', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(
        #[MapRequestPayload] ChangePasswordRequest $dto,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $dto->currentPassword)) {
            return $this->json(
                ['error' => 'Invalid current password'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($dto->newPassword === $dto->currentPassword) {
            return $this->json(
                ['error' => 'New password cannot be identical to current password.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Password successfully updated',
        ], Response::HTTP_OK);
    }

    private function formatUserProfile(User $user): array
    {
        $garage = $user->getGarage();
        $fullName = (string) $user->getFullName();
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        return [
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
        ];
    }
}
