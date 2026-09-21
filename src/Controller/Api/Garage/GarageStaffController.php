<?php

namespace App\Controller\Api\Garage;

use App\DTO\Garage\CreateStaffRequest;
use App\Entity\Garage;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage/users', name: 'api_garage_users_')]
#[IsGranted('ROLE_GARAGE_ADMIN')]
final class GarageStaffController extends AbstractController
{
    private const ROLE_MAP = [
        'mechanic' => 'ROLE_MECHANIC',
        'role_mechanic' => 'ROLE_MECHANIC',
        'admin' => 'ROLE_GARAGE_ADMIN',
        'role_garage_admin' => 'ROLE_GARAGE_ADMIN',
    ];

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $includeDeleted = $request->query->getBoolean('include_deleted');
        $staff = $this->userRepository->findByGarage($garage, $includeDeleted);

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
        }, $staff);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateStaffRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
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

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->json(
                ['error' => sprintf('User with email "%s" already exists.', $dto->email)],
                Response::HTTP_CONFLICT
            );
        }

        return $this->json([
            'id' => $user->getId()?->toRfc4122(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $garage = $currentUser->getGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $targetUser = $this->userRepository->findOneBy(['id' => $id, 'garage' => $garage]);
        } catch (\Throwable) {
            $targetUser = null;
        }

        if (!$targetUser instanceof User || $targetUser->isDeleted()) {
            return $this->json(['error' => 'Staff member not found'], Response::HTTP_NOT_FOUND);
        }

        // Self-deletion guard
        if ($targetUser->getId()?->toRfc4122() === $currentUser->getId()?->toRfc4122()) {
            return $this->json(['error' => 'Cannot delete the currently authenticated user'], Response::HTTP_BAD_REQUEST);
        }

        // Admin-on-admin deletion protection
        if (in_array('ROLE_GARAGE_ADMIN', $targetUser->getRoles(), true)) {
            return $this->json(
                ['error' => 'Cannot delete a workshop administrator. Contact platform support.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Defense-in-depth last admin guard
        if (in_array('ROLE_GARAGE_ADMIN', $targetUser->getRoles(), true)) {
            $remainingAdmins = $this->userRepository->countActiveAdminsByGarageExcluding($garage, $targetUser);
            if ($remainingAdmins <= 0) {
                return $this->json(
                    ['error' => 'Cannot delete the last active administrator of a garage'],
                    Response::HTTP_CONFLICT
                );
            }
        }

        // Soft-delete and clean up sensitive credentials
        $targetUser->setDeletedAt(new \DateTimeImmutable());
        $targetUser->setIsActive(false);
        $targetUser->setInvitationTokenHash(null);
        $targetUser->setInvitationExpiresAt(null);
        $targetUser->setPassword('*');

        $this->entityManager->flush();

        return $this->json(['message' => 'Staff member successfully deleted'], Response::HTTP_OK);
    }

    private function resolveCurrentGarage(): ?Garage
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getGarage();
    }
}
