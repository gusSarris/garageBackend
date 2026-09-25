<?php

namespace App\Controller\Api\Garage;

use App\DTO\Garage\Settings\UpdateGarageSettingsRequest;
use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage/settings', name: 'api_garage_settings_')]
final class GarageSettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_MECHANIC')]
    public function getSettings(): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($garage->getSettings());
    }

    #[Route('', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_GARAGE_ADMIN')]
    public function updateSettings(
        #[MapRequestPayload] UpdateGarageSettingsRequest $dto,
    ): JsonResponse {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $newSettings = array_replace_recursive($garage->getRawSettings(), $dto->toArray());
        $garage->setSettings($newSettings);
        $garage->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json($garage->getSettings());
    }

    private function resolveCurrentGarage(): ?Garage
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getGarage();
    }
}
