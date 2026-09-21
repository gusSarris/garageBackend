<?php

namespace App\Controller\Api\Auth;

use App\DTO\Auth\AcceptInvitationRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/invitation', name: 'api_auth_invitation_')]
final class InvitationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Target('invitation')]
        private readonly RateLimiterFactoryInterface $invitationLimiter,
    ) {
    }

    #[Route('/{token}', name: 'verify', methods: ['GET'])]
    public function verify(string $token, Request $request): JsonResponse
    {
        $limiter = $this->invitationLimiter->create($request->getClientIp() ?? '127.0.0.1');
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->userRepository->findOneBy(['invitationTokenHash' => $tokenHash]);

        if (
            $user === null
            || $user->getInvitationExpiresAt() === null
            || $user->getInvitationExpiresAt() < new \DateTimeImmutable()
            || $user->isActive()
        ) {
            return new JsonResponse(
                ['error' => 'Invalid or expired invitation token'],
                Response::HTTP_NOT_FOUND,
                ['Cache-Control' => 'no-store']
            );
        }

        $garage = $user->getGarage();

        return new JsonResponse([
            'valid' => true,
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'garage' => [
                'name' => $garage?->getName(),
                'city' => $garage?->getCity(),
            ],
        ], Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    #[Route('/accept', name: 'accept', methods: ['POST'])]
    public function accept(
        #[MapRequestPayload] AcceptInvitationRequest $dto,
        Request $request,
    ): JsonResponse {
        $limiter = $this->invitationLimiter->create($request->getClientIp() ?? '127.0.0.1');
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['error' => 'Too many requests.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $tokenHash = hash('sha256', $dto->token);

        /** @var User|null $activatedUser */
        $activatedUser = $this->entityManager->wrapInTransaction(function () use ($dto, $tokenHash): ?User {
            $user = $this->entityManager->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.invitationTokenHash = :hash')
                ->setParameter('hash', $tokenHash)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            if (!$user instanceof User) {
                return null;
            }

            if (
                $user->getInvitationExpiresAt() === null
                || $user->getInvitationExpiresAt() < new \DateTimeImmutable()
                || $user->isActive()
            ) {
                return null;
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
            if ($dto->fullName !== null && trim($dto->fullName) !== '') {
                $user->setFullName($dto->fullName);
            }
            $user->setIsActive(true);
            $user->setInvitationTokenHash(null);
            $user->setInvitationExpiresAt(null);

            return $user;
        });

        if ($activatedUser === null) {
            return new JsonResponse(
                ['error' => 'Invalid or expired invitation token'],
                Response::HTTP_NOT_FOUND,
                ['Cache-Control' => 'no-store']
            );
        }

        $jwt = $this->jwtManager->create($activatedUser);
        $garage = $activatedUser->getGarage();

        return new JsonResponse([
            'token' => $jwt,
            'user' => [
                'id' => $activatedUser->getId()?->toRfc4122(),
                'email' => $activatedUser->getEmail(),
                'fullName' => $activatedUser->getFullName(),
                'roles' => $activatedUser->getRoles(),
            ],
            'garage' => $garage ? [
                'id' => $garage->getId()?->toRfc4122(),
                'name' => $garage->getName(),
                'subscriptionStatus' => $garage->getSubscriptionStatus(),
            ] : null,
        ], Response::HTTP_OK);
    }
}
