<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Creates a new Platform Super Admin user without tenant garage binding (CLI-only)',
)]
class CreateSuperAdminCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Email address of the super admin')] string $email,
        #[Argument('Full name of the super admin')] string $fullName,
        #[Option('Force creation even if a super admin already exists')] bool $force = false,
    ): int {
        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $io->error(sprintf('User with email "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $superAdminCount = $this->userRepository->countSuperAdmins();
        if ($superAdminCount > 0 && !$force) {
            $io->error('A Platform Super Admin already exists. Pass --force to create another.');

            return Command::FAILURE;
        }

        if ($superAdminCount > 0 && $force) {
            $io->warning('Existing Platform Super Admin detected. Creating additional super admin due to --force.');
            $this->logger?->warning(sprintf('Platform Super Admin created with --force override for email "%s"', $email));
        }

        $password = $io->askHidden('Enter password for the Super Admin');
        if (empty($password)) {
            $io->error('Password cannot be empty.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setIsActive(true);
        $user->setGarage(null);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Platform Super Admin "%s" successfully created with ID %s.', $email, $user->getId()?->toRfc4122()));

        return Command::SUCCESS;
    }
}
