<?php

namespace App\Tests\Garage;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GarageProfileTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createGarageAdmin(string $password = 'AdminPass123!'): array
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Garage ' . $unique);
        $garage->setEmail('garage_' . $unique . '@example.com');
        $garage->setPhone('+30 210 1234567');
        $garage->setVatNumber('EL' . rand(100000000, 999999999));
        $garage->setTaxOffice('Tax Office ' . $unique);
        $garage->setAddress('Address ' . $unique);
        $garage->setCity('City ' . $unique);
        $garage->setPostalCode('12345');
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);

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
        $garage->setName('Garage Mech ' . $unique);
        $garage->setEmail('garage_mech_' . $unique . '@example.com');
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

    public function testGarageAdminCanViewOwnProfile(): void
    {
        [$user, $garage] = $this->createGarageAdmin();
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

        $this->assertSame($garage->getId()->toRfc4122(), $data['id']);
        $this->assertSame($garage->getName(), $data['name']);
        $this->assertSame($garage->getEmail(), $data['email']);
        $this->assertSame($garage->getPhone(), $data['phone']);
        $this->assertSame($garage->getVatNumber(), $data['vatNumber']);
        $this->assertSame($garage->getTaxOffice(), $data['taxOffice']);
        $this->assertSame($garage->getAddress(), $data['address']);
        $this->assertSame($garage->getCity(), $data['city']);
        $this->assertSame($garage->getPostalCode(), $data['postalCode']);
        $this->assertSame($garage->getSubscriptionStatus(), $data['subscriptionStatus']);
        $this->assertSame(true, $data['isActive']);
        $this->assertNotNull($data['createdAt']);
    }

    public function testGarageAdminCanUpdateProfile(): void
    {
        [$user, $garage] = $this->createGarageAdmin();
        $token = $this->getJwtToken($user);

        $payload = [
            'name' => 'Auto Moto Service Sarris Updated',
            'phone' => '+30 210 9999999',
            'address' => 'Leoforos Vouliagmenis 200',
            'city' => 'Glyfada',
            'postalCode' => '16675',
        ];

        $this->client->request(
            'PATCH',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($payload)
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('Auto Moto Service Sarris Updated', $data['name']);
        $this->assertSame('+30 210 9999999', $data['phone']);
        $this->assertSame('Leoforos Vouliagmenis 200', $data['address']);
        $this->assertSame('Glyfada', $data['city']);
        $this->assertSame('16675', $data['postalCode']);
        $this->assertNotNull($data['updatedAt']);

        // Verify database persistence
        $this->entityManager->clear();
        $refreshed = $this->entityManager->find(Garage::class, $garage->getId());
        $this->assertNotNull($refreshed);
        $this->assertSame('Auto Moto Service Sarris Updated', $refreshed->getName());
        $this->assertSame('+30 210 9999999', $refreshed->getPhone());
        $this->assertSame('Leoforos Vouliagmenis 200', $refreshed->getAddress());
        $this->assertSame('Glyfada', $refreshed->getCity());
        $this->assertSame('16675', $refreshed->getPostalCode());
        $this->assertNotNull($refreshed->getUpdatedAt());
    }

    public function testProtectedFieldsAreNotModifiedOnUpdate(): void
    {
        [$user, $garage] = $this->createGarageAdmin();
        $token = $this->getJwtToken($user);

        $originalVatNumber = $garage->getVatNumber();
        $originalTaxOffice = $garage->getTaxOffice();
        $originalSubscriptionStatus = $garage->getSubscriptionStatus();
        $originalIsActive = $garage->isActive();
        $originalCreatedAt = $garage->getCreatedAt()->format(\DateTimeInterface::ATOM);

        $maliciousPayload = [
            'name' => 'Valid Updated Name',
            'vatNumber' => 'EL000000000',
            'taxOffice' => 'Fake Tax Office',
            'subscriptionStatus' => 'cancelled',
            'isActive' => false,
            'createdAt' => '2020-01-01T00:00:00+00:00',
        ];

        $this->client->request(
            'PATCH',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($maliciousPayload)
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Allowed field updated
        $this->assertSame('Valid Updated Name', $data['name']);

        // Protected fields untouched in response
        $this->assertSame($originalVatNumber, $data['vatNumber']);
        $this->assertSame($originalTaxOffice, $data['taxOffice']);
        $this->assertSame($originalSubscriptionStatus, $data['subscriptionStatus']);
        $this->assertSame($originalIsActive, $data['isActive']);
        $this->assertSame($originalCreatedAt, $data['createdAt']);

        // Verify database state is untouched
        $this->entityManager->clear();
        $refreshed = $this->entityManager->find(Garage::class, $garage->getId());
        $this->assertNotNull($refreshed);
        $this->assertSame('Valid Updated Name', $refreshed->getName());
        $this->assertSame($originalVatNumber, $refreshed->getVatNumber());
        $this->assertSame($originalTaxOffice, $refreshed->getTaxOffice());
        $this->assertSame($originalSubscriptionStatus, $refreshed->getSubscriptionStatus());
        $this->assertSame($originalIsActive, $refreshed->isActive());
    }

    public function testMechanicForbiddenFromUpdatingProfile(): void
    {
        [$mechanic, $garage] = $this->createMechanic();
        $token = $this->getJwtToken($mechanic);

        // GET /api/garage
        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        $this->assertResponseStatusCodeSame(403);

        // PATCH /api/garage
        $this->client->request(
            'PATCH',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['name' => 'Hacked Name'])
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testGarageAdminForbiddenFromCreatingGarages(): void
    {
        [$user, $garage] = $this->createGarageAdmin();
        $token = $this->getJwtToken($user);

        // POST /api/garage -> Method Not Allowed (405)
        $this->client->request(
            'POST',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['name' => 'Illegal Second Garage'])
        );
        $this->assertResponseStatusCodeSame(405);

        // POST /api/admin/garages -> Forbidden (403)
        $this->client->request(
            'POST',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'name' => 'Illegal Garage',
                'email' => 'illegal@garage.com',
                'adminEmail' => 'illegal_admin@garage.com',
                'adminFullName' => 'Illegal Admin',
                'adminPassword' => 'Password123!',
            ])
        );
        $this->assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminWithoutGarageReturnsBadRequest(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        // GET /api/garage
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
        $this->assertArrayHasKey('error', $data);

        // PATCH /api/garage
        $this->client->request(
            'PATCH',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['name' => 'Super Admin Workshop'])
        );
        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUnauthenticatedForbidden(): void
    {
        // GET /api/garage without auth
        $this->client->request('GET', '/api/garage');
        $this->assertResponseStatusCodeSame(401);

        // PATCH /api/garage without auth
        $this->client->request(
            'PATCH',
            '/api/garage',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'Anonymous Update'])
        );
        $this->assertResponseStatusCodeSame(401);
    }

    public function testInvalidEmailReturns422(): void
    {
        [$user, $garage] = $this->createGarageAdmin();
        $token = $this->getJwtToken($user);

        $this->client->request(
            'PATCH',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['email' => 'not-a-valid-email'])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testTenantIsolationBetweenGarages(): void
    {
        [$userA, $garageA] = $this->createGarageAdmin();
        [$userB, $garageB] = $this->createGarageAdmin();

        $tokenA = $this->getJwtToken($userA);

        // User A views own profile
        $this->client->request(
            'GET',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        $this->assertResponseStatusCodeSame(200);
        $dataA = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($garageA->getId()->toRfc4122(), $dataA['id']);
        $this->assertNotSame($garageB->getId()->toRfc4122(), $dataA['id']);

        // User A updates own profile
        $this->client->request(
            'PATCH',
            '/api/garage',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['name' => 'Garage A New Brand'])
        );
        $this->assertResponseStatusCodeSame(200);

        // Verify Garage B is completely untouched
        $this->entityManager->clear();
        $refreshedB = $this->entityManager->find(Garage::class, $garageB->getId());
        $this->assertNotNull($refreshedB);
        $this->assertSame($garageB->getName(), $refreshedB->getName());
        $this->assertNotSame('Garage A New Brand', $refreshedB->getName());
    }
}
