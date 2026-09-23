<?php

namespace App\Tests;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RouteProtectionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createGarage(string $prefix = 'sec_garage'): Garage
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Garage ' . $prefix . ' ' . $unique);
        $garage->setEmail($prefix . '_' . $unique . '@example.com');
        $garage->setPhone('+30 210 ' . rand(1000000, 9999999));
        $garage->setVatNumber('EL' . rand(100000000, 999999999));
        $garage->setTaxOffice('DOY ' . $unique);
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);

        $this->entityManager->persist($garage);
        $this->entityManager->flush();

        return $garage;
    }

    private function createUser(?Garage $garage, string $role = 'ROLE_MECHANIC', string $password = 'TestPass123!'): User
    {
        $unique = bin2hex(random_bytes(4));

        $user = new User();
        $user->setEmail('user_' . $unique . '@example.com');
        $user->setFullName('User ' . $unique);
        $user->setRoles([$role]);
        $user->setIsActive(true);
        if ($garage instanceof Garage) {
            $user->setGarage($garage);
        }
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
        $this->assertSame(200, $response->getStatusCode(), 'Authentication failed: ' . $response->getContent());

        $data = json_decode((string) $response->getContent(), true);

        return (string) $data['token'];
    }

    #[DataProvider('protectedRoutesProvider')]
    public function testProtectedApiRoutesRejectUnauthenticatedAccess(string $method, string $url): void
    {
        $this->client->request(
            $method,
            $url,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(
            401,
            $response->getStatusCode(),
            sprintf('Expected 401 for unauthenticated %s %s, got %d', $method, $url, $response->getStatusCode())
        );
    }

    public static function protectedRoutesProvider(): array
    {
        return [
            ['GET', '/api/auth/me'],
            ['PATCH', '/api/auth/me'],
            ['POST', '/api/auth/change-password'],
            ['GET', '/api/garage'],
            ['PATCH', '/api/garage'],
            ['GET', '/api/garage/lookup'],
            ['GET', '/api/garage/work-orders'],
            ['POST', '/api/garage/work-orders'],
            ['GET', '/api/garage/vehicles'],
            ['POST', '/api/garage/vehicles'],
            ['GET', '/api/garage/customers'],
            ['POST', '/api/garage/customers'],
            ['GET', '/api/garage/users'],
            ['POST', '/api/garage/users'],
            ['GET', '/api/admin/garages'],
            ['POST', '/api/admin/garages'],
        ];
    }

    public function testAdminRoutesRejectMechanicsWithForbidden(): void
    {
        $garage = $this->createGarage();
        $mechanic = $this->createUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'GET',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testStaffManagementRejectsMechanicsWithForbidden(): void
    {
        $garage = $this->createGarage();
        $mechanic = $this->createUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'GET',
            '/api/garage/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPublicEndpointsDoNotRequireAuthentication(): void
    {
        // 1. Login check endpoint accepts credentials without Bearer token
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'unknown@example.com', 'password' => 'wrong'])
        );
        $this->assertNotSame(404, $this->client->getResponse()->getStatusCode());

        // 2. Invitation token lookup is public
        $this->client->request(
            'GET',
            '/api/auth/invitation/invalid-test-token',
            server: ['HTTP_ACCEPT' => 'application/json']
        );
        // It returns 404/410 because token doesn't exist, NOT 401 Unauthorized
        $this->assertContains($this->client->getResponse()->getStatusCode(), [404, 410]);
    }

    public function testNonExistentFrontendRouteReturns404Cleanly(): void
    {
        $this->client->request('GET', '/queue');
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/dashboard');
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
    }
}
