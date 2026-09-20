<?php

namespace App\Controller\Api\Admin;

use App\DTO\Admin\CreateGarageRequest;
use App\DTO\Admin\UpdateSubscriptionRequest;
use App\Entity\Garage;
use App\Entity\User;
use App\Repository\GarageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/garages', name: 'api_admin_garages_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class GarageManagementController extends AbstractController
{
    public function __construct(
        private readonly GarageRepository $garageRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $garages = $this->garageRepository->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(function (Garage $garage): array {
            return [
                'id' => $garage->getId()?->toRfc4122(),
                'name' => $garage->getName(),
                'email' => $garage->getEmail(),
                'phone' => $garage->getPhone(),
                'vatNumber' => $garage->getVatNumber(),
                'city' => $garage->getCity(),
                'subscriptionStatus' => $garage->getSubscriptionStatus(),
                'isActive' => $garage->isActive(),
                'createdAt' => $garage->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'stats' => [
                    'userCount' => $garage->getUsers()->count(),
                    'customerCount' => $garage->getCustomers()->count(),
                    'vehicleCount' => $garage->getVehicles()->count(),
                    'workOrderCount' => $garage->getWorkOrders()->count(),
                ],
            ];
        }, $garages);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateGarageRequest $dto,
    ): JsonResponse {
        if ($this->userRepository->findOneBy(['email' => $dto->adminEmail]) !== null) {
            return $this->json(
                ['error' => sprintf('User with email "%s" already exists.', $dto->adminEmail)],
                Response::HTTP_CONFLICT
            );
        }

        $garage = new Garage();
        $garage->setName($dto->name);
        $garage->setEmail($dto->email);
        $garage->setPhone($dto->phone);
        $garage->setVatNumber($dto->vatNumber);
        $garage->setAddress($dto->address);
        $garage->setCity($dto->city);
        $garage->setPostalCode($dto->postalCode);
        $garage->setSubscriptionStatus('trial');
        $garage->setIsActive(true);

        $admin = new User();
        $admin->setEmail($dto->adminEmail);
        $admin->setFullName($dto->adminFullName);
        $admin->setRoles(['ROLE_GARAGE_ADMIN']);
        $admin->setIsActive(true);
        $admin->setGarage($garage);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $dto->adminPassword));

        $this->entityManager->wrapInTransaction(function () use ($garage, $admin): void {
            $this->entityManager->persist($garage);
            $this->entityManager->persist($admin);
        });

        return $this->json([
            'garage' => [
                'id' => $garage->getId()?->toRfc4122(),
                'name' => $garage->getName(),
                'email' => $garage->getEmail(),
                'phone' => $garage->getPhone(),
                'vatNumber' => $garage->getVatNumber(),
                'address' => $garage->getAddress(),
                'city' => $garage->getCity(),
                'postalCode' => $garage->getPostalCode(),
                'subscriptionStatus' => $garage->getSubscriptionStatus(),
                'isActive' => $garage->isActive(),
                'createdAt' => $garage->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
            'admin' => [
                'id' => $admin->getId()?->toRfc4122(),
                'email' => $admin->getEmail(),
                'fullName' => $admin->getFullName(),
                'roles' => $admin->getRoles(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/subscription', name: 'update_subscription', methods: ['PATCH'])]
    public function updateSubscription(
        string $id,
        #[MapRequestPayload] UpdateSubscriptionRequest $dto,
    ): JsonResponse {
        $garage = $this->garageRepository->find($id);
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'Garage not found'], Response::HTTP_NOT_FOUND);
        }

        $garage->setSubscriptionStatus($dto->subscriptionStatus);
        if ($dto->isActive !== null) {
            $garage->setIsActive($dto->isActive);
        }
        $garage->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json([
            'garage' => [
                'id' => $garage->getId()?->toRfc4122(),
                'name' => $garage->getName(),
                'email' => $garage->getEmail(),
                'phone' => $garage->getPhone(),
                'vatNumber' => $garage->getVatNumber(),
                'subscriptionStatus' => $garage->getSubscriptionStatus(),
                'isActive' => $garage->isActive(),
                'updatedAt' => $garage->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
