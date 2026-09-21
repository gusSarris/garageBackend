<?php

namespace App\Tests\Security;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TenantStatusListenerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createWorkshop(
        string $subscriptionStatus = 'active',
        bool $isActive = true,
        string $role = 'ROLE_GARAGE_ADMIN',
        string $password = 'TestPass123!',
    ): array {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Workshop ' . $unique);
        $garage->setEmail('workshop_' . $unique . '@example.com');
        $garage->setPhone('+30 210 1234567');
        $garage->setVatNumber('EL' . rand(100000000, 999999999));
        $garage->setTaxOffice('Tax ' . $unique);
        $garage->setAddress('Street ' . $unique);
        $garage->setCity('City ' . $unique);
        $garage->setPostalCode('12345');
        $garage->setSubscriptionStatus($subscriptionStatus);
        $garage->setIsActive($isActive);

        $this->entityManager->persist($garage);

        $user = new User();
        $user->setEmail('user_' . $unique . '@example.com');
        $user->setFullName('User ' . $unique);
        $user->setRoles([$role]);
        $user->setIsActive(true);
        $user->setGarage($garage);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $garage];
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

    private function getJwtToken(User $user, string $password = 'TestPass123!'): string
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

    public function testActiveWorkshopWithTrialSubscriptionCanAccess(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'trial', isActive: true);
        $token = $this->getJwtToken($user);

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['isActive']);
        $this->assertSame('trial', $data['subscriptionStatus']);
    }

    public function testActiveWorkshopWithActiveSubscriptionCanAccess(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'active', isActive: true);
        $token = $this->getJwtToken($user);

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['isActive']);
        $this->assertSame('active', $data['subscriptionStatus']);
    }

    public function testInactiveWorkshopIsBlockedWith403(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'active', isActive: false);
        $token = $this->getJwtToken($user);

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Workshop is inactive or subscription has expired.', $data['error']);
        $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code']);
        $this->assertFalse($data['isActive']);
        $this->assertSame('active', $data['subscriptionStatus']);
    }

    public function testCancelledSubscriptionIsBlockedWith403(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'cancelled', isActive: true);
        $token = $this->getJwtToken($user);

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Workshop is inactive or subscription has expired.', $data['error']);
        $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code']);
        $this->assertTrue($data['isActive']);
        $this->assertSame('cancelled', $data['subscriptionStatus']);
    }

    public function testExpiredSubscriptionIsBlockedWith403(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'expired', isActive: true);
        $token = $this->getJwtToken($user);

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Workshop is inactive or subscription has expired.', $data['error']);
        $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code']);
        $this->assertTrue($data['isActive']);
        $this->assertSame('expired', $data['subscriptionStatus']);
    }

    public function testPastDueSubscriptionIsBlockedWith403(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'past_due', isActive: true);
        $token = $this->getJwtToken($user);

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Workshop is inactive or subscription has expired.', $data['error']);
        $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code']);
        $this->assertTrue($data['isActive']);
        $this->assertSame('past_due', $data['subscriptionStatus']);
    }

    public function testAllGarageSubroutesAreBlocked(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'active', isActive: false);
        $token = $this->getJwtToken($user);

        $endpoints = [
            ['GET', '/api/garage'],
            ['PATCH', '/api/garage', ['name' => 'Attempted Rename']],
            ['GET', '/api/garage/users'],
            ['POST', '/api/garage/users', ['email' => 'newstaff@test.com', 'fullName' => 'New Staff', 'password' => 'SecurePass123!', 'role' => 'mechanic']],
            ['GET', '/api/garage/customers'],
            ['POST', '/api/garage/customers', ['firstName' => 'John', 'lastName' => 'Doe', 'phone' => '+306912345678']],
            ['GET', '/api/garage/vehicles'],
            ['POST', '/api/garage/vehicles', ['licensePlate' => 'ABC-1234', 'make' => 'Toyota', 'model' => 'Yaris']],
            ['GET', '/api/garage/work-orders'],
            ['POST', '/api/garage/work-orders', ['description' => 'Oil change', 'date' => '2026-09-22', 'status' => 'pending']],
        ];

        foreach ($endpoints as $endpoint) {
            $method = $endpoint[0];
            $uri = $endpoint[1];
            $payload = $endpoint[2] ?? null;

            $this->client->request(
                $method,
                $uri,
                server: [
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                    'HTTP_ACCEPT' => 'application/json',
                    'CONTENT_TYPE' => 'application/json',
                ],
                content: $payload !== null ? json_encode($payload) : null,
            );

            $this->assertResponseStatusCodeSame(403, sprintf('Route %s %s was not blocked by TenantStatusListener', $method, $uri));
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code'] ?? null);
        }
    }

    public function testAuthMeRemainsAccessibleForInactiveWorkshop(): void
    {
        [$user, $garage] = $this->createWorkshop(subscriptionStatus: 'cancelled', isActive: false);
        $token = $this->getJwtToken($user);

        // /api/auth/me should succeed
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

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($user->getEmail(), $data['email']);
        $this->assertSame('cancelled', $data['garage']['subscriptionStatus']);

        // While /api/garage with same token is blocked
        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminBypassesTenantGuard(): void
    {
        $superAdmin = $this->createSuperAdmin('SuperSecret123!');
        $token = $this->getJwtToken($superAdmin, 'SuperSecret123!');

        // Platform operations remain unaffected
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

        // Super Admin accessing /api/garage returns 400 (no garage) rather than 403 (inactive guard)
        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('No garage associated with this account.', $data['error']);
    }

    public function testMechanicBlockedWhenWorkshopInactive(): void
    {
        [$mechanic, $garage] = $this->createWorkshop(
            subscriptionStatus: 'active',
            isActive: false,
            role: 'ROLE_MECHANIC',
        );
        $token = $this->getJwtToken($mechanic);

        // Mechanic trying to access work orders or customers is blocked
        $this->client->request(
            'GET',
            '/api/garage/customers',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code']);

        $this->client->request(
            'GET',
            '/api/garage/work-orders',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(403);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('WORKSHOP_INACTIVE_OR_EXPIRED', $data['code']);
    }

    public function testUserWithoutGarageReturns400(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = new User();
        $user->setEmail('nogarage_' . $unique . '@example.com');
        $user->setFullName('No Garage User');
        $user->setRoles(['ROLE_GARAGE_ADMIN']);
        $user->setIsActive(true);
        $user->setGarage(null);
        $user->setPassword(password_hash('TestPass123!', PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->getJwtToken($user, 'TestPass123!');

        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('No garage associated with this account.', $data['error']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
