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

class GarageManagementTest extends WebTestCase
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

    private function createGarageAdmin(string $password = 'AdminPass123!'): array
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Garage ' . $unique);
        $garage->setEmail('garage_' . $unique . '@example.com');
        $this->entityManager->persist($garage);

        $user = new User();
        $user->setEmail('admin_' . $unique . '@example.com');
        $user->setFullName('Garage Admin ' . $unique);
        $user->setRoles(['ROLE_GARAGE_ADMIN']);
        $user->setIsActive(true);
        $user->setGarage($garage);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $garage];
    }

    private function createMechanic(string $password = 'AdminPass123!'): array
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Garage ' . $unique);
        $garage->setEmail('garage_' . $unique . '@example.com');
        $this->entityManager->persist($garage);

        $user = new User();
        $user->setEmail('mech_' . $unique . '@example.com');
        $user->setFullName('Mechanic ' . $unique);
        $user->setRoles(['ROLE_MECHANIC']);
        $user->setIsActive(true);
        $user->setGarage($garage);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $garage];
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
        $this->assertSame(200, $response->getStatusCode(), 'Failed to authenticate user: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);

        return $data['token'];
    }

    public function testSuperAdminCanListGarages(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        // Ensure at least one garage exists
        $this->createGarageAdmin();

        $this->client->request(
            'GET',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $firstGarage = $data[0];
        $this->assertArrayHasKey('id', $firstGarage);
        $this->assertArrayHasKey('name', $firstGarage);
        $this->assertArrayHasKey('email', $firstGarage);
        $this->assertArrayHasKey('phone', $firstGarage);
        $this->assertArrayHasKey('vatNumber', $firstGarage);
        $this->assertArrayHasKey('city', $firstGarage);
        $this->assertArrayHasKey('subscriptionStatus', $firstGarage);
        $this->assertArrayHasKey('isActive', $firstGarage);
        $this->assertArrayHasKey('createdAt', $firstGarage);
        $this->assertArrayHasKey('stats', $firstGarage);

        $stats = $firstGarage['stats'];
        $this->assertArrayHasKey('userCount', $stats);
        $this->assertArrayHasKey('customerCount', $stats);
        $this->assertArrayHasKey('vehicleCount', $stats);
        $this->assertArrayHasKey('workOrderCount', $stats);
    }

    public function testGarageAdminForbiddenFromPlatformEndpoints(): void
    {
        [$garageAdmin] = $this->createGarageAdmin();
        $token = $this->getJwtToken($garageAdmin);

        $this->client->request(
            'GET',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMechanicForbiddenFromPlatformEndpoints(): void
    {
        [$mechanic] = $this->createMechanic();
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'GET',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedForbiddenFromPlatformEndpoints(): void
    {
        $this->client->request(
            'GET',
            '/api/admin/garages',
            server: ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testSuperAdminCanOnboardNewGarage(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $unique = bin2hex(random_bytes(4));
        $payload = [
            'name' => 'Auto Service Elite ' . $unique,
            'email' => 'contact_' . $unique . '@autoservice.gr',
            'phone' => '+30 210 1234567',
            'vatNumber' => 'EL998877665',
            'address' => 'Leoforos Athinon 100',
            'city' => 'Athens',
            'postalCode' => '10447',
            'adminEmail' => 'admin_' . $unique . '@autoservice.gr',
            'adminFullName' => 'Νίκος Οικονόμου',
            'adminPassword' => 'WorkshopSecurePass2026!',
        ];

        $this->client->request(
            'POST',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('garage', $data);
        $this->assertArrayHasKey('admin', $data);

        $garageData = $data['garage'];
        $this->assertSame($payload['name'], $garageData['name']);
        $this->assertSame($payload['email'], $garageData['email']);
        $this->assertSame($payload['phone'], $garageData['phone']);
        $this->assertSame($payload['vatNumber'], $garageData['vatNumber']);
        $this->assertSame('trial', $garageData['subscriptionStatus']);
        $this->assertTrue($garageData['isActive']);

        $adminData = $data['admin'];
        $this->assertSame($payload['adminEmail'], $adminData['email']);
        $this->assertSame($payload['adminFullName'], $adminData['fullName']);
        $this->assertContains('ROLE_GARAGE_ADMIN', $adminData['roles']);

        // Verify entities exist in database
        $userRepo = $this->entityManager->getRepository(User::class);
        $persistedAdmin = $userRepo->findOneBy(['email' => $payload['adminEmail']]);
        $this->assertNotNull($persistedAdmin);
        $this->assertNotNull($persistedAdmin->getGarage());
        $this->assertSame($garageData['id'], $persistedAdmin->getGarage()->getId()?->toRfc4122());
    }

    public function testSuperAdminCanUpdateSubscription(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        [, $garage] = $this->createGarageAdmin();
        $garageId = $garage->getId()->toRfc4122();

        $updatePayload = [
            'subscriptionStatus' => 'active',
            'isActive' => true,
        ];

        $this->client->request(
            'PATCH',
            '/api/admin/garages/' . $garageId . '/subscription',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($updatePayload)
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('garage', $data);
        $this->assertSame('active', $data['garage']['subscriptionStatus']);
        $this->assertTrue($data['garage']['isActive']);

        // Verify update persisted in DB
        $this->entityManager->clear();
        $updatedGarage = $this->entityManager->getRepository(Garage::class)->find($garageId);
        $this->assertNotNull($updatedGarage);
        $this->assertSame('active', $updatedGarage->getSubscriptionStatus());
        $this->assertTrue($updatedGarage->isActive());
    }

    public function testAuthMeForSuperAdmin(): void
    {
        $superAdmin = $this->createSuperAdmin('SuperSecret123!');
        $token = $this->getJwtToken($superAdmin, 'SuperSecret123!');

        $this->client->request(
            'GET',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $profile = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($profile);
        $this->assertSame($superAdmin->getId()->toRfc4122(), $profile['id']);
        $this->assertSame($superAdmin->getEmail(), $profile['email']);
        $this->assertContains('ROLE_SUPER_ADMIN', $profile['roles']);
        $this->assertNull($profile['garage']);
    }

    public function testCreateSuperAdminCommand(): void
    {
        $application = new Application(static::$kernel);
        $command = $application->find('app:create-super-admin');
        $commandTester = new CommandTester($command);

        $unique = bin2hex(random_bytes(4));
        $email = 'cli_admin_' . $unique . '@clickdrive.io';
        $fullName = 'CLI Platform Admin ' . $unique;

        $exitCode = $commandTester->execute([
            'email' => $email,
            'password' => 'CliPassword123!',
            'full-name' => $fullName,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $commandTester->getDisplay());
        $this->assertStringContainsString('successfully created', $display);

        // Verify user in DB
        $userRepo = $this->entityManager->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => $email]);

        $this->assertNotNull($user);
        $this->assertSame($fullName, $user->getFullName());
        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
        $this->assertTrue($user->isSuperAdmin());
        $this->assertNull($user->getGarage());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
