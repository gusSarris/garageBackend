<?php

namespace App\Tests\Admin;

use App\Entity\Garage;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GarageUserManagementTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createSuperAdmin(string $password = 'AdminPass123!'): User
    {
        $unique = bin2hex(random_bytes(4));

        $user = new User();
        $user->setEmail('superadmin_' . $unique . '@clickdrive.io');
        $user->setFullName('Platform Super Admin');
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setIsActive(true);
        $user->setGarage(null);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createGarage(string $prefix = 'garage'): Garage
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Garage ' . $prefix . ' ' . $unique);
        $garage->setEmail($prefix . '_' . $unique . '@example.com');
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);

        $this->entityManager->persist($garage);
        $this->entityManager->flush();

        return $garage;
    }

    private function createGarageUser(Garage $garage, string $role = 'ROLE_MECHANIC', string $password = 'UserPass123!'): User
    {
        $unique = bin2hex(random_bytes(4));

        $user = new User();
        $user->setEmail('user_' . $unique . '@example.com');
        $user->setFullName('User ' . $unique);
        $user->setRoles([$role]);
        $user->setIsActive(true);
        $user->setGarage($garage);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function getJwtToken(User $user, string $password = 'AdminPass123!'): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => $password,
            ])
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), 'Failed to authenticate: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);

        return $data['token'];
    }

    public function testSuperAdminCanListGarageUsers(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage = $this->createGarage('list');
        $user1 = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $user2 = $this->createGarageUser($garage, 'ROLE_MECHANIC');

        $this->client->request(
            'GET',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        $emails = array_column($data, 'email');
        $this->assertContains($user1->getEmail(), $emails);
        $this->assertContains($user2->getEmail(), $emails);

        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('fullName', $data[0]);
        $this->assertArrayHasKey('roles', $data[0]);
        $this->assertArrayHasKey('isActive', $data[0]);
        $this->assertArrayHasKey('createdAt', $data[0]);
    }

    public function testListGarageUsersReturns404ForNonexistentGarage(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $this->client->request(
            'GET',
            '/api/admin/garages/018f0000-0000-7000-8000-000000000000/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Garage not found', $data['error']);
    }

    public function testSuperAdminCanCreateGarageAdminAndMechanic(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage = $this->createGarage('create');
        $garageId = $garage->getId()->toRfc4122();

        // 1. Create mechanic
        $unique = bin2hex(random_bytes(4));
        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garageId . '/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'mechanic_' . $unique . '@example.com',
                'fullName' => 'Nikos Mechanic',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('mechanic_' . $unique . '@example.com', $data['email']);
        $this->assertSame('Nikos Mechanic', $data['fullName']);
        $this->assertContains('ROLE_MECHANIC', $data['roles']);
        $this->assertTrue($data['isActive']);
        $this->assertSame($garageId, $data['garage']['id']);

        // 2. Create garage admin
        $uniqueAdmin = bin2hex(random_bytes(4));
        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garageId . '/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'admin_' . $uniqueAdmin . '@example.com',
                'fullName' => 'Costas Admin',
                'password' => 'SecurePass123!',
                'role' => 'admin',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $adminData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertContains('ROLE_GARAGE_ADMIN', $adminData['roles']);
    }

    public function testCreateGarageUserFailsOnDuplicateEmail(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage = $this->createGarage('dup');
        $existing = $this->createGarageUser($garage);

        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $existing->getEmail(),
                'fullName' => 'Duplicate Person',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );

        $this->assertResponseStatusCodeSame(409);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already exists', $data['error']);
    }

    public function testCreateGarageUserFailsOnInvalidRole(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage = $this->createGarage('invalid_role');

        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'test_' . bin2hex(random_bytes(4)) . '@example.com',
                'fullName' => 'Invalid Role',
                'password' => 'SecurePass123!',
                'role' => 'super_owner',
            ])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateGarageUserFailsOnInvalidGarageId(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $this->client->request(
            'POST',
            '/api/admin/garages/018f0000-0000-7000-8000-000000000000/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'test_' . bin2hex(random_bytes(4)) . '@example.com',
                'fullName' => 'Valid Person',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testSuperAdminCanDeleteGarageUser(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage = $this->createGarage('del');
        $user = $this->createGarageUser($garage);
        $userId = $user->getId()->toRfc4122();

        $this->client->request(
            'DELETE',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users/' . $userId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(200);

        // Verify entity deleted
        $userRepo = static::getContainer()->get(UserRepository::class);
        $this->assertNull($userRepo->find($userId));
    }

    public function testDeleteGarageUserFailsIfUserBelongsToDifferentGarage(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage1 = $this->createGarage('g1');
        $garage2 = $this->createGarage('g2');

        // User belongs to garage 1
        $user = $this->createGarageUser($garage1);
        $userId = $user->getId()->toRfc4122();

        // Attempt to delete user via garage 2 route
        $this->client->request(
            'DELETE',
            '/api/admin/garages/' . $garage2->getId()->toRfc4122() . '/users/' . $userId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('User does not belong to the specified garage', $data['error']);

        // Confirm user was NOT deleted
        $userRepo = static::getContainer()->get(UserRepository::class);
        $this->assertNotNull($userRepo->find($userId));
    }

    public function testDeleteGarageUserFailsIfUserNotFound(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $garage = $this->createGarage('not_found');

        $this->client->request(
            'DELETE',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users/018f0000-0000-7000-8000-000000000000',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGarageAdminForbiddenFromAdminUserEndpoints(): void
    {
        $garage = $this->createGarage('forbidden');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin, 'UserPass123!');

        $this->client->request(
            'GET',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMechanicForbiddenFromAdminUserEndpoints(): void
    {
        $garage = $this->createGarage('forbidden_mech');
        $mech = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mech, 'UserPass123!');

        $this->client->request(
            'GET',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedForbiddenFromAdminUserEndpoints(): void
    {
        $garage = $this->createGarage('unauth');

        $this->client->request(
            'GET',
            '/api/admin/garages/' . $garage->getId()->toRfc4122() . '/users'
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateGarageUserCommandWithGeneratedPassword(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $garage = $this->createGarage('cli1');
        $command = $application->find('app:create-garage-user');
        $commandTester = new CommandTester($command);

        $unique = bin2hex(random_bytes(4));
        $email = 'cli_user_' . $unique . '@example.com';

        $commandTester->execute([
            'garage-identifier' => $garage->getId()->toRfc4122(),
            'email' => $email,
            'full-name' => 'CLI Mechanic',
            'role' => 'mechanic',
        ]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('successfully created', $output);
        $this->assertStringContainsString('Auto-generated temporary password', $output);

        $userRepo = static::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertSame('CLI Mechanic', $user->getFullName());
        $this->assertContains('ROLE_MECHANIC', $user->getRoles());
        $this->assertSame($garage->getId()->toRfc4122(), $user->getGarage()->getId()->toRfc4122());
    }

    public function testCreateGarageUserCommandWithExplicitPassword(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $garage = $this->createGarage('cli2');
        $command = $application->find('app:create-garage-user');
        $commandTester = new CommandTester($command);

        $unique = bin2hex(random_bytes(4));
        $email = 'cli_admin_' . $unique . '@example.com';

        $commandTester->execute([
            'garage-identifier' => $garage->getEmail(),
            'email' => $email,
            'full-name' => 'CLI Admin',
            'role' => 'admin',
            '--password' => 'ExplicitPass123!',
        ]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('successfully created', $output);
        $this->assertStringNotContainsString('Auto-generated temporary password', $output);

        $userRepo = static::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertContains('ROLE_GARAGE_ADMIN', $user->getRoles());
    }

    public function testCreateGarageUserCommandFailsOnDuplicateEmail(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $garage = $this->createGarage('cli3');
        $existing = $this->createGarageUser($garage);

        $command = $application->find('app:create-garage-user');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'garage-identifier' => $garage->getId()->toRfc4122(),
            'email' => $existing->getEmail(),
            'full-name' => 'Duplicate CLI',
            'role' => 'mechanic',
        ]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('already exists', $commandTester->getDisplay());
    }

    public function testCreateGarageUserCommandFailsOnInvalidRole(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $garage = $this->createGarage('cli4');

        $command = $application->find('app:create-garage-user');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'garage-identifier' => $garage->getId()->toRfc4122(),
            'email' => 'invalid_role_' . bin2hex(random_bytes(4)) . '@example.com',
            'full-name' => 'Invalid Role CLI',
            'role' => 'unknown_role',
        ]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid role', $commandTester->getDisplay());
    }

    public function testCreateGarageUserCommandFailsOnNonexistentGarage(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:create-garage-user');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'garage-identifier' => '018f0000-0000-7000-8000-000000000000',
            'email' => 'valid_' . bin2hex(random_bytes(4)) . '@example.com',
            'full-name' => 'Valid User CLI',
            'role' => 'mechanic',
        ]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Garage "018f0000-0000-7000-8000-000000000000" not found', $commandTester->getDisplay());
    }
}
