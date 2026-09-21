<?php

namespace App\Command;

use App\Entity\Garage;
use App\Entity\User;
use App\Repository\GarageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-garage-user',
    description: 'Securely provisions a user for a specific garage tenant',
)]
class CreateGarageUserCommand
{
    private const ROLE_MAP = [
        'admin' => 'ROLE_GARAGE_ADMIN',
        'role_garage_admin' => 'ROLE_GARAGE_ADMIN',
        'mechanic' => 'ROLE_MECHANIC',
        'role_mechanic' => 'ROLE_MECHANIC',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly GarageRepository $garageRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('UUID or Email of the Garage')] string $garageIdentifier,
        #[Argument('Email address of the new user')] string $email,
        #[Argument('Full name of the user')] string $fullName,
        #[Argument('Role: "admin" (ROLE_GARAGE_ADMIN) or "mechanic" (ROLE_MECHANIC)')] string $role = 'mechanic',
        #[Option('Explicit password (if omitted, a secure random one is generated)')] ?string $password = null,
    ): int {
        $roleKey = strtolower(trim($role));
        if (!isset(self::ROLE_MAP[$roleKey])) {
            $io->error(sprintf('Invalid role "%s". Allowed choices: admin, mechanic, ROLE_GARAGE_ADMIN, ROLE_MECHANIC.', $role));

            return Command::FAILURE;
        }
        $targetRole = self::ROLE_MAP[$roleKey];

        // Resolve garage by UUID or email
        $garage = null;
        try {
            $garage = $this->garageRepository->find($garageIdentifier);
        } catch (\Throwable) {
            // Identifier might not be a valid UUID format
            $garage = null;
        }

        if (!$garage instanceof Garage) {
            $garage = $this->garageRepository->findOneBy(['email' => $garageIdentifier]);
        }

        if (!$garage instanceof Garage) {
            $io->error(sprintf('Garage "%s" not found.', $garageIdentifier));

            return Command::FAILURE;
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $io->error(sprintf('User with email "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $generatedPassword = false;
        if ($password === null || trim($password) === '') {
            $password = bin2hex(random_bytes(8));
            $generatedPassword = true;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles([$targetRole]);
        $user->setIsActive(true);
        $user->setGarage($garage);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            'User "%s" successfully created for garage "%s" with role %s (ID: %s).',
            $email,
            $garage->getName(),
            $targetRole,
            $user->getId()?->toRfc4122()
        ));

        if ($generatedPassword) {
            $io->warning(sprintf('Auto-generated temporary password: %s', $password));
            $io->note('Please advise the user to update their password upon first login.');
        }

        return Command::SUCCESS;
    }
}
