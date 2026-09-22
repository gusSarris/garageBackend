<?php

namespace App\Tests\Cors;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CorsHeadersTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createTestUser(string $password = 'TestPass123!'): array
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('CORS Test Garage ' . $unique);
        $garage->setEmail('cors_' . $unique . '@garage.example.com');
        $this->entityManager->persist($garage);

        $user = new User();
        $user->setEmail('cors_user_' . $unique . '@example.com');
        $user->setFullName('CORS Tester');
        $user->setRoles(['ROLE_GARAGE_ADMIN']);
        $user->setGarage($garage);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $garage];
    }

    public function testPreflightRequestOnLoginCheck(): void
    {
        $this->client->request(
            'OPTIONS',
            '/api/login_check',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:3000',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertTrue(in_array($response->getStatusCode(), [200, 204], true));
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', (string) $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testPreflightRequestOnProtectedApiRoute(): void
    {
        $this->client->request(
            'OPTIONS',
            '/api/garage/customers',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:5173',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertTrue(in_array($response->getStatusCode(), [200, 204], true));
        $this->assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', (string) $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testActualRequestIncludesCorsHeaders(): void
    {
        [$user] = $this->createTestUser('ValidPass123!');

        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:3000',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'ValidPass123!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAuthenticatedRequestIncludesCorsHeaders(): void
    {
        [$user] = $this->createTestUser('OwnerPass123!');

        // 1. Obtain JWT token
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'OwnerPass123!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $authData = json_decode($this->client->getResponse()->getContent(), true);
        $token = $authData['token'];

        // 2. Call authenticated endpoint with Origin
        $this->client->request(
            'GET',
            '/api/auth/me',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:3000',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testDisallowedOriginDoesNotGetCorsHeaders(): void
    {
        $this->client->request(
            'OPTIONS',
            '/api/login_check',
            server: [
                'HTTP_ORIGIN' => 'http://malicious-site.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
