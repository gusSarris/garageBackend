<?php

namespace App\Controller\Api\Garage;

use App\DTO\Garage\UpdateGarageProfileRequest;
use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage', name: 'api_garage_')]
#[IsGranted('ROLE_GARAGE_ADMIN')]
final class GarageProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $garage = $user->getGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->formatGarageResponse($garage));
    }

    #[Route('', name: 'update_profile', methods: ['PATCH'])]
    public function updateProfile(
        #[MapRequestPayload] UpdateGarageProfileRequest $dto,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $garage = $user->getGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        if ($dto->name !== null) {
            $garage->setName($dto->name);
        }
        if ($dto->email !== null) {
            $garage->setEmail($dto->email);
        }
        if ($dto->phone !== null) {
            $garage->setPhone($dto->phone);
        }
        if ($dto->address !== null) {
            $garage->setAddress($dto->address);
        }
        if ($dto->city !== null) {
            $garage->setCity($dto->city);
        }
        if ($dto->postalCode !== null) {
            $garage->setPostalCode($dto->postalCode);
        }

        $garage->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->formatGarageResponse($garage));
    }

    private function formatGarageResponse(Garage $garage): array
    {
        return [
            'id' => $garage->getId()?->toRfc4122(),
            'name' => $garage->getName(),
            'email' => $garage->getEmail(),
            'phone' => $garage->getPhone(),
            'vatNumber' => $garage->getVatNumber(),
            'taxOffice' => $garage->getTaxOffice(),
            'address' => $garage->getAddress(),
            'city' => $garage->getCity(),
            'postalCode' => $garage->getPostalCode(),
            'subscriptionStatus' => $garage->getSubscriptionStatus(),
            'isActive' => $garage->isActive(),
            'createdAt' => $garage->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $garage->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
