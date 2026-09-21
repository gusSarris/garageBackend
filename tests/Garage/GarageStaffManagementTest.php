<?php

namespace App\Tests\Garage;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GarageStaffManagementTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createGarage(string $prefix = 'garage'): Garage
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Garage ' . $prefix . ' ' . $unique);
        $garage->setEmail($prefix . '_' . $unique . '@example.com');
        $garage->setPhone('+30 210 1234567');
        $garage->setVatNumber('EL' . rand(100000000, 999999999));
        $garage->setTaxOffice('DOY ' . $unique);
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);

        $this->entityManager->persist($garage);
        $this->entityManager->flush();

        return $garage;
    }

    private function createGarageUser(Garage $garage, string $role = 'ROLE_GARAGE_ADMIN', string $password = 'AdminPass123!'): User
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

    private function createSuperAdmin(string $password = 'AdminPass123!'): User
    {
        $unique = bin2hex(random_bytes(4));

        $user = new User();
        $user->setEmail('superadmin_' . $unique . '@clickdrive.io');
        $user->setFullName('Platform Super Admin ' . $unique);
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

    public function testGarageAdminCanListStaff(): void
    {
        $garage1 = $this->createGarage('one');
        $admin1 = $this->createGarageUser($garage1, 'ROLE_GARAGE_ADMIN');
        $mechanic1 = $this->createGarageUser($garage1, 'ROLE_MECHANIC');

        $garage2 = $this->createGarage('two');
        $admin2 = $this->createGarageUser($garage2, 'ROLE_GARAGE_ADMIN');
        $mechanic2 = $this->createGarageUser($garage2, 'ROLE_MECHANIC');

        $token = $this->getJwtToken($admin1);

        $this->client->request(
            'GET',
            '/api/garage/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $emails = array_column($data, 'email');

        // Must include Garage 1 users
        $this->assertContains($admin1->getEmail(), $emails);
        $this->assertContains($mechanic1->getEmail(), $emails);

        // Must NOT include Garage 2 users (Cross-tenant isolation)
        $this->assertNotContains($admin2->getEmail(), $emails);
        $this->assertNotContains($mechanic2->getEmail(), $emails);
    }

    public function testGarageAdminCanCreateMechanic(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin);

        $email = 'mechanic_' . bin2hex(random_bytes(4)) . '@example.com';

        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $email,
                'fullName' => 'Kostas Papas',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame($email, $data['email']);
        $this->assertSame('Kostas Papas', $data['fullName']);
        $this->assertContains('ROLE_MECHANIC', $data['roles']);
        $this->assertTrue($data['isActive']);
        $this->assertArrayNotHasKey('password', $data);

        // Verify entity in database
        $createdUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($createdUser);
        $this->assertSame($garage->getId()?->toRfc4122(), $createdUser->getGarage()?->getId()?->toRfc4122());
        $this->assertContains('ROLE_MECHANIC', $createdUser->getRoles());
    }

    public function testGarageAdminCanCreateCoAdmin(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin);

        $email = 'coadmin_' . bin2hex(random_bytes(4)) . '@example.com';

        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $email,
                'fullName' => 'Elena Vasiliou',
                'password' => 'SecurePass123!',
                'role' => 'admin',
            ])
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame($email, $data['email']);
        $this->assertContains('ROLE_GARAGE_ADMIN', $data['roles']);

        $createdUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($createdUser);
        $this->assertContains('ROLE_GARAGE_ADMIN', $createdUser->getRoles());
    }

    public function testCreateStaffFailsOnDuplicateEmail(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $existingUser = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($admin);

        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $existingUser->getEmail(),
                'fullName' => 'Duplicate Person',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );

        $this->assertSame(409, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already exists', $data['error']);
    }

    public function testCreateStaffRejectsSuperAdminRole(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin);

        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'escalate_' . bin2hex(random_bytes(4)) . '@example.com',
                'fullName' => 'Hacker Trying Super Admin',
                'password' => 'SecurePass123!',
                'role' => 'ROLE_SUPER_ADMIN',
            ])
        );

        $this->assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateStaffEnforcesPasswordPolicy(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin);

        // Password shorter than 10 characters
        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'shortpass_' . bin2hex(random_bytes(4)) . '@example.com',
                'fullName' => 'Short Pass User',
                'password' => 'short123',
                'role' => 'mechanic',
            ])
        );

        $this->assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testGarageAdminCanSoftDeleteStaff(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $mechanicPassword = 'MechanicPass123!';
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC', $mechanicPassword);

        $adminToken = $this->getJwtToken($admin);
        $mechanicToken = $this->getJwtToken($mechanic, $mechanicPassword);

        // Pre-check mechanic token works
        $this->client->request(
            'GET',
            '/api/auth/me',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // Admin soft-deletes the mechanic
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $mechanic->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Staff member successfully deleted', $data['message']);

        // Refresh entity with soft-delete filter disabled
        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled('soft_delete')) {
            $filters->disable('soft_delete');
        }
        $refreshed = $this->entityManager->getRepository(User::class)->find($mechanic->getId());
        $this->assertNotNull($refreshed);
        $this->assertTrue($refreshed->isDeleted());
        $this->assertNotNull($refreshed->getDeletedAt());
        $this->assertFalse($refreshed->isActive());
        $this->assertSame('*', $refreshed->getPassword());
        if (!$filters->isEnabled('soft_delete')) {
            $filters->enable('soft_delete');
        }

        // Assert mechanic's existing JWT token is now rejected (401 Unauthorized via UserChecker)
        $this->client->request(
            'GET',
            '/api/auth/me',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken]
        );
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        // Assert re-creating a user with the same email succeeds (email reuse with partial index)
        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $mechanic->getEmail(),
                'fullName' => 'Rehired Mechanic',
                'password' => 'NewSecurePass123!',
                'role' => 'mechanic',
            ])
        );
        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteStaffFailsForCrossTenant(): void
    {
        $garage1 = $this->createGarage('one');
        $admin1 = $this->createGarageUser($garage1, 'ROLE_GARAGE_ADMIN');

        $garage2 = $this->createGarage('two');
        $mechanic2 = $this->createGarageUser($garage2, 'ROLE_MECHANIC');

        $token1 = $this->getJwtToken($admin1);

        // Admin 1 attempts to delete Mechanic 2 from Garage 2
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $mechanic2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token1]
        );

        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Staff member not found', $data['error']);
    }

    public function testDeleteStaffFailsOnSelfDeletion(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin);

        // Admin attempts to delete their own account
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $admin->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Cannot delete the currently authenticated user', $data['error']);
    }

    public function testDeleteStaffFailsWhenTargetIsAdmin(): void
    {
        $garage = $this->createGarage();
        $admin1 = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $admin2 = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');

        $token = $this->getJwtToken($admin1);

        // Admin 1 attempts to delete Co-Admin 2
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $admin2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Cannot delete a workshop administrator', $data['error']);
    }

    public function testDeleteAlreadyDeletedUserReturns404(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($admin);

        // First delete -> 200 OK
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $mechanic->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // Second delete -> 404 Not Found
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $mechanic->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdminWithoutGarageReturnsBadRequest(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        // GET without garage
        $this->client->request(
            'GET',
            '/api/garage/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());

        // POST without garage
        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'test_' . bin2hex(random_bytes(4)) . '@example.com',
                'fullName' => 'Test User',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());

        // DELETE without garage
        $this->client->request(
            'DELETE',
            '/api/garage/users/019213ab-0000-7000-8000-000000000000',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testMechanicForbiddenFromManagingStaff(): void
    {
        $garage = $this->createGarage();
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);

        // GET
        $this->client->request(
            'GET',
            '/api/garage/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // POST
        $this->client->request(
            'POST',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'forbidden_' . bin2hex(random_bytes(4)) . '@example.com',
                'fullName' => 'Forbidden User',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // DELETE
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $mechanic->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedForbidden(): void
    {
        // GET
        $this->client->request('GET', '/api/garage/users');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        // POST
        $this->client->request(
            'POST',
            '/api/garage/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com'])
        );
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        // DELETE
        $this->client->request('DELETE', '/api/garage/users/019213ab-0000-7000-8000-000000000000');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testListStaffSupportsIncludeDeleted(): void
    {
        $garage = $this->createGarage();
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($admin);

        // Delete mechanic
        $this->client->request(
            'DELETE',
            '/api/garage/users/' . $mechanic->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // Default list (active only)
        $this->client->request(
            'GET',
            '/api/garage/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $activeUsers = json_decode($this->client->getResponse()->getContent(), true);
        $activeEmails = array_column($activeUsers, 'email');
        $this->assertNotContains($mechanic->getEmail(), $activeEmails);

        // List with ?include_deleted=true
        $this->client->request(
            'GET',
            '/api/garage/users?include_deleted=true',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $allUsers = json_decode($this->client->getResponse()->getContent(), true);
        $allEmails = array_column($allUsers, 'email');
        $this->assertContains($mechanic->getEmail(), $allEmails);

        // Check deletedAt is present on the soft-deleted user
        foreach ($allUsers as $u) {
            if ($u['email'] === $mechanic->getEmail()) {
                $this->assertNotNull($u['deletedAt']);
                $this->assertFalse($u['isActive']);
            }
        }
    }
}
