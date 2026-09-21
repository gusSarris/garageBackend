<?php

namespace App\Service;

use App\DTO\Admin\CreateGarageRequest;
use App\Entity\Garage;
use App\Entity\User;
use App\Repository\GarageRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GarageProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly GarageRepository $garageRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%app.frontend_url%')]
        private readonly string $frontendUrl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function provision(CreateGarageRequest $dto): array
    {
        $ownerEmail = $dto->getOwnerEmail();
        if ($this->userRepository->findOneBy(['email' => $ownerEmail]) !== null) {
            throw new ConflictHttpException(sprintf('User with email "%s" already exists.', $ownerEmail));
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new \DateTimeImmutable('+7 days');

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
        $admin->setEmail($ownerEmail);
        $admin->setFullName($dto->getOwnerFullName());
        $admin->setRoles(['ROLE_GARAGE_ADMIN']);
        $admin->setIsActive(false);
        $admin->setGarage($garage);
        $admin->setInvitationTokenHash($tokenHash);
        $admin->setInvitationExpiresAt($expiresAt);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, bin2hex(random_bytes(16))));

        try {
            $this->entityManager->wrapInTransaction(function () use ($garage, $admin): void {
                $this->entityManager->persist($garage);
                $this->entityManager->persist($admin);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException(sprintf('User with email "%s" already exists.', $ownerEmail), $e);
        }

        $invitationUrl = sprintf('%s/activate?token=%s', rtrim($this->frontendUrl, '/'), $rawToken);

        return [
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
                'isActive' => $admin->isActive(),
            ],
            'invitation' => [
                'token' => $rawToken,
                'invitationUrl' => $invitationUrl,
                'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resendInvite(Garage $garage): array
    {
        $owner = null;
        foreach ($garage->getUsers() as $user) {
            if (in_array('ROLE_GARAGE_ADMIN', $user->getRoles(), true)) {
                $owner = $user;
                break;
            }
        }

        if ($owner === null) {
            $owner = $this->userRepository->findOneBy(['garage' => $garage]);
        }

        if ($owner === null) {
            throw new NotFoundHttpException('No administrator found for this garage.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = new \DateTimeImmutable('+7 days');

        $owner->setInvitationTokenHash($tokenHash);
        $owner->setInvitationExpiresAt($expiresAt);

        $this->entityManager->flush();

        $invitationUrl = sprintf('%s/activate?token=%s', rtrim($this->frontendUrl, '/'), $rawToken);

        return [
            'garageId' => $garage->getId()?->toRfc4122(),
            'ownerEmail' => $owner->getEmail(),
            'token' => $rawToken,
            'invitationUrl' => $invitationUrl,
            'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
