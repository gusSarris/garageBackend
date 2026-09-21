<?php

namespace App\Controller\Api\Admin;

use App\DTO\Admin\CreateGarageUserRequest;
use App\Entity\Garage;
use App\Entity\User;
use App\Repository\GarageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/garages/{garageId}/users', name: 'api_admin_garage_users_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class GarageUserController extends AbstractController
{
    private const ROLE_MAP = [
        'admin' => 'ROLE_GARAGE_ADMIN',
        'role_garage_admin' => 'ROLE_GARAGE_ADMIN',
        'mechanic' => 'ROLE_MECHANIC',
        'role_mechanic' => 'ROLE_MECHANIC',
    ];

    public function __construct(
        private readonly GarageRepository $garageRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(string $garageId, Request $request): JsonResponse
    {
        $garage = $this->resolveGarage($garageId);
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'Garage not found'], Response::HTTP_NOT_FOUND);
        }

        $includeDeleted = $request->query->getBoolean('include_deleted');
        $users = $this->userRepository->findByGarage($garage, $includeDeleted);

        $data = array_map(function (User $user): array {
            return [
                'id' => $user->getId()?->toRfc4122(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'deletedAt' => $user->getDeletedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $users);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        string $garageId,
        #[MapRequestPayload] CreateGarageUserRequest $dto,
    ): JsonResponse {
        $garage = $this->resolveGarage($garageId);
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'Garage not found'], Response::HTTP_NOT_FOUND);
        }

        if ($this->userRepository->findOneBy(['email' => $dto->email]) !== null) {
            return $this->json(
                ['error' => sprintf('User with email "%s" already exists.', $dto->email)],
                Response::HTTP_CONFLICT
            );
        }

        $roleKey = strtolower(trim($dto->role));
        $targetRole = self::ROLE_MAP[$roleKey] ?? 'ROLE_MECHANIC';

        $user = new User();
        $user->setEmail($dto->email);
        $user->setFullName($dto->fullName);
        $user->setRoles([$targetRole]);
        $user->setIsActive(true);
        $user->setGarage($garage);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'id' => $user->getId()?->toRfc4122(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'garage' => [
                'id' => $garage->getId()?->toRfc4122(),
                'name' => $garage->getName(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{userId}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $garageId, string $userId): JsonResponse
    {
        $garage = $this->resolveGarage($garageId);
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'Garage not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->resolveUser($userId);
        if (!$user instanceof User || $user->isDeleted()) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify user belongs to this specific garage
        if ($user->getGarage()?->getId()?->toRfc4122() !== $garage->getId()?->toRfc4122()) {
            return $this->json(['error' => 'User does not belong to the specified garage'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId()?->toRfc4122() === $user->getId()?->toRfc4122()) {
            return $this->json(['error' => 'Cannot delete the currently authenticated user'], Response::HTTP_BAD_REQUEST);
        }

        // Last owner guard
        if (in_array('ROLE_GARAGE_ADMIN', $user->getRoles(), true)) {
            $activeAdminsCount = $this->userRepository->countActiveAdminsByGarage($garage);
            if ($activeAdminsCount <= 1) {
                return $this->json(
                    ['error' => 'Cannot delete the last active administrator of a garage'],
                    Response::HTTP_CONFLICT
                );
            }
        }

        // Soft delete and clean up sensitive credentials
        $user->setDeletedAt(new \DateTimeImmutable());
        $user->setIsActive(false);
        $user->setInvitationTokenHash(null);
        $user->setInvitationExpiresAt(null);
        $user->setPassword('*');

        $this->entityManager->flush();

        return $this->json(['message' => 'User successfully deleted'], Response::HTTP_OK);
    }

    private function resolveGarage(string $garageId): ?Garage
    {
        try {
            return $this->garageRepository->find($garageId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveUser(string $userId): ?User
    {
        try {
            return $this->userRepository->find($userId);
        } catch (\Throwable) {
            return null;
        }
    }
}
