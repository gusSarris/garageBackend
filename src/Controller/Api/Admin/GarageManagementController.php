<?php

namespace App\Controller\Api\Admin;

use App\DTO\Admin\CreateGarageRequest;
use App\DTO\Admin\UpdateSubscriptionRequest;
use App\Entity\Garage;
use App\Repository\GarageRepository;
use App\Service\GarageProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/garages', name: 'api_admin_garages_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class GarageManagementController extends AbstractController
{
    public function __construct(
        private readonly GarageRepository $garageRepository,
        private readonly GarageProvisioner $garageProvisioner,
        private readonly EntityManagerInterface $entityManager,
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
        $result = $this->garageProvisioner->provision($dto);

        return $this->json($result, Response::HTTP_CREATED);
    }

    #[Route('/{id}/resend-invite', name: 'resend_invite', methods: ['POST'])]
    public function resendInvite(string $id): JsonResponse
    {
        $garage = $this->garageRepository->find($id);
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'Garage not found'], Response::HTTP_NOT_FOUND);
        }

        $result = $this->garageProvisioner->resendInvite($garage);

        return $this->json($result);
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
