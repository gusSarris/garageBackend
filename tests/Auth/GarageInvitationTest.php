<?php

namespace App\Tests\Auth;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GarageInvitationTest extends WebTestCase
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

    private function getJwtToken(User $user, string $password = 'AdminPass123!'): string
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => $password,
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        return $data['token'] ?? '';
    }

    public function testSecondSuperAdminRequiresForce(): void
    {
        $this->createSuperAdmin();

        $application = new Application(static::$kernel);
        $command = $application->find('app:create-super-admin');
        $commandTester = new CommandTester($command);

        $unique = bin2hex(random_bytes(4));
        $email = 'second_superadmin_' . $unique . '@clickdrive.io';
        $fullName = 'Second Super Admin';

        // Attempt without --force should fail
        $commandTester->setInputs(['SuperPassword123!']);
        $exitCode = $commandTester->execute([
            'email' => $email,
            'full-name' => $fullName,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Pass --force to create another', $commandTester->getDisplay());

        // Attempt with --force should succeed
        $commandTester->setInputs(['SuperPassword123!']);
        $exitCodeForce = $commandTester->execute([
            'email' => $email,
            'full-name' => $fullName,
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCodeForce);
        $this->assertStringContainsString('successfully created', $commandTester->getDisplay());
    }

    public function testSuperAdminCanInviteGarageOwner(): array
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $unique = bin2hex(random_bytes(4));
        $payload = [
            'name' => 'Auto Service Pro ' . $unique,
            'email' => 'garage_' . $unique . '@autoservice.gr',
            'phone' => '+30 210 1122334',
            'vatNumber' => 'EL112233445',
            'address' => 'Kifisias 50',
            'city' => 'Athens',
            'postalCode' => '11526',
            'ownerEmail' => 'owner_' . $unique . '@autoservice.gr',
            'ownerFullName' => 'Γιάννης Παπαδόπουλος',
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
        $this->assertArrayHasKey('invitation', $data);

        $this->assertSame($payload['name'], $data['garage']['name']);
        $this->assertFalse($data['admin']['isActive']);
        $this->assertContains('ROLE_GARAGE_ADMIN', $data['admin']['roles']);

        $invitation = $data['invitation'];
        $this->assertNotEmpty($invitation['token']);
        $this->assertStringContainsString($invitation['token'], $invitation['invitationUrl']);
        $this->assertNotEmpty($invitation['expiresAt']);

        // Verify token in DB is hashed and not plaintext
        $this->entityManager->clear();
        $userRepo = $this->entityManager->getRepository(User::class);
        $owner = $userRepo->findOneBy(['email' => $payload['ownerEmail']]);
        $this->assertNotNull($owner);
        $this->assertSame(hash('sha256', $invitation['token']), $owner->getInvitationTokenHash());
        $this->assertFalse($owner->isActive());

        return [
            'garageId' => $data['garage']['id'],
            'ownerEmail' => $payload['ownerEmail'],
            'rawToken' => $invitation['token'],
        ];
    }

    public function testInviteDuplicateOwnerEmailRejected(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $existingUser = $this->createSuperAdmin();

        $unique = bin2hex(random_bytes(4));
        $payload = [
            'name' => 'Auto Duplicate ' . $unique,
            'email' => 'duplicate_' . $unique . '@autoservice.gr',
            'ownerEmail' => $existingUser->getEmail(),
            'ownerFullName' => 'Duplicate Owner',
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

        $this->assertResponseStatusCodeSame(409);
    }

    public function testVerifyInvitationTokenValid(): void
    {
        $setup = $this->testSuperAdminCanInviteGarageOwner();
        $rawToken = $setup['rawToken'];

        $this->client->request(
            'GET',
            '/api/auth/invitation/' . $rawToken,
            server: ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(200);
        $this->assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['valid']);
        $this->assertSame($setup['ownerEmail'], $data['email']);
        $this->assertArrayHasKey('garage', $data);
    }

    public function testVerifyInvitationTokenExpiredOrInvalid(): void
    {
        $this->client->request(
            'GET',
            '/api/auth/invitation/invalid-nonexistent-token-12345',
            server: ['HTTP_ACCEPT' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(404);
        $this->assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Invalid or expired invitation token', $data['error']);
    }

    public function testExpiredTokenRejected(): void
    {
        $setup = $this->testSuperAdminCanInviteGarageOwner();
        $rawToken = $setup['rawToken'];

        // Expire token in database
        $userRepo = $this->entityManager->getRepository(User::class);
        $owner = $userRepo->findOneBy(['email' => $setup['ownerEmail']]);
        $owner->setInvitationExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        // Verify should return 404
        $this->client->request(
            'GET',
            '/api/auth/invitation/' . $rawToken,
            server: ['HTTP_ACCEPT' => 'application/json']
        );
        $this->assertResponseStatusCodeSame(404);

        // Accept should also return 404
        $this->client->request(
            'POST',
            '/api/auth/invitation/accept',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'token' => $rawToken,
                'password' => 'NewSecurePassword2026!',
            ])
        );
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPendingUserCannotLogin(): void
    {
        $setup = $this->testSuperAdminCanInviteGarageOwner();

        // Attempting to log in as pending owner must fail
        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $setup['ownerEmail'],
                'password' => 'AnyPassword123!',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAcceptInvitationSetsPasswordAndReturnsJwt(): array
    {
        $setup = $this->testSuperAdminCanInviteGarageOwner();
        $rawToken = $setup['rawToken'];

        $newPassword = 'OwnerChosenPassword2026!';

        $this->client->request(
            'POST',
            '/api/auth/invitation/accept',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'token' => $rawToken,
                'password' => $newPassword,
                'fullName' => 'Γιάννης Παπαδόπουλος Updated',
            ])
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
        $this->assertSame($setup['ownerEmail'], $data['user']['email']);
        $this->assertSame('Γιάννης Παπαδόπουλος Updated', $data['user']['fullName']);
        $this->assertContains('ROLE_GARAGE_ADMIN', $data['user']['roles']);

        // Check user state in DB
        $this->entityManager->clear();
        $userRepo = $this->entityManager->getRepository(User::class);
        $owner = $userRepo->findOneBy(['email' => $setup['ownerEmail']]);
        $this->assertTrue($owner->isActive());
        $this->assertNull($owner->getInvitationTokenHash());
        $this->assertNull($owner->getInvitationExpiresAt());

        return [
            'ownerEmail' => $setup['ownerEmail'],
            'password' => $newPassword,
            'rawToken' => $rawToken,
        ];
    }

    public function testTokenIsSingleUse(): void
    {
        $accepted = $this->testAcceptInvitationSetsPasswordAndReturnsJwt();
        $rawToken = $accepted['rawToken'];

        // Second accept with the same token must fail
        $this->client->request(
            'POST',
            '/api/auth/invitation/accept',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'token' => $rawToken,
                'password' => 'AnotherPassword2026!',
            ])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testOwnerCanLoginWithNewPassword(): void
    {
        $accepted = $this->testAcceptInvitationSetsPasswordAndReturnsJwt();

        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $accepted['ownerEmail'],
                'password' => $accepted['password'],
            ])
        );

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testResendInvalidatesOldToken(): void
    {
        $setup = $this->testSuperAdminCanInviteGarageOwner();
        $garageId = $setup['garageId'];
        $oldToken = $setup['rawToken'];

        $superAdmin = $this->createSuperAdmin();
        $adminToken = $this->getJwtToken($superAdmin);

        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garageId . '/resend-invite',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $newToken = $data['token'];

        $this->assertNotSame($oldToken, $newToken);

        // Old token should be 404
        $this->client->request(
            'GET',
            '/api/auth/invitation/' . $oldToken,
            server: ['HTTP_ACCEPT' => 'application/json']
        );
        $this->assertResponseStatusCodeSame(404);

        // New token should be 200
        $this->client->request(
            'GET',
            '/api/auth/invitation/' . $newToken,
            server: ['HTTP_ACCEPT' => 'application/json']
        );
        $this->assertResponseStatusCodeSame(200);
    }

    public function testNoRouteGrantsSuperAdmin(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $unique = bin2hex(random_bytes(4));
        $payload = [
            'name' => 'Auto Attempt ' . $unique,
            'email' => 'attempt_' . $unique . '@autoservice.gr',
            'ownerEmail' => 'attempt_owner_' . $unique . '@autoservice.gr',
            'ownerFullName' => 'Attempt Super',
            'roles' => ['ROLE_SUPER_ADMIN'],
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

        // Created user must strictly have ROLE_GARAGE_ADMIN, never ROLE_SUPER_ADMIN
        $this->assertNotContains('ROLE_SUPER_ADMIN', $data['admin']['roles']);
        $this->assertContains('ROLE_GARAGE_ADMIN', $data['admin']['roles']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
