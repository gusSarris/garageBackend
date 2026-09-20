<?php

namespace App\Tests;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createTestUserAndGarage(string $password = 'TestPass123!'): array
    {
        $unique = bin2hex(random_bytes(4));

        $garage = new Garage();
        $garage->setName('Auto Moto Service ' . $unique);
        $garage->setEmail('contact_' . $unique . '@garage.example.com');
        $this->entityManager->persist($garage);

        $user = new User();
        $user->setEmail('owner_' . $unique . '@garage.example.com');
        $user->setFullName('Γιώργος Παπαδόπουλος');
        $user->setRoles(['ROLE_GARAGE_ADMIN']);
        $user->setGarage($garage);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $garage];
    }

    public function testLoginSuccessWithValidCredentials(): void
    {
        [$user, $garage] = $this->createTestUserAndGarage('ValidPass123!');

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'ValidPass123!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('token', $data);

        $token = $data['token'];
        $this->assertNotEmpty($token);

        // Decode JWT payload without signature verification to inspect claims
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertIsArray($payload);
        $this->assertSame($user->getEmail(), $payload['email'] ?? null);
        $this->assertSame($user->getId()->toRfc4122(), $payload['id'] ?? null);
        $this->assertSame('Γιώργος Παπαδόπουλος', $payload['fullName'] ?? null);
        $this->assertSame($garage->getId()->toRfc4122(), $payload['garageId'] ?? null);
        $this->assertSame($garage->getName(), $payload['garageName'] ?? null);
        $this->assertContains('ROLE_GARAGE_ADMIN', $payload['roles'] ?? []);
    }

    public function testLoginFailureWithInvalidPassword(): void
    {
        [$user] = $this->createTestUserAndGarage('CorrectPassword123!');

        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'WrongPassword!',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginFailureWithNonExistentUser(): void
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'nonexistent_' . bin2hex(random_bytes(4)) . '@example.com',
                'password' => 'AnyPassword123!',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAuthMeUnauthenticated(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAuthMeSuccess(): void
    {
        [$user, $garage] = $this->createTestUserAndGarage('OwnerPass123!');

        // Login first to get JWT token
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

        // Request /api/auth/me with Bearer token
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

        $this->assertSame($user->getId()->toRfc4122(), $profile['id']);
        $this->assertSame($user->getEmail(), $profile['email']);
        $this->assertSame('Γιώργος', $profile['firstName']);
        $this->assertSame('Παπαδόπουλος', $profile['lastName']);
        $this->assertContains('ROLE_GARAGE_ADMIN', $profile['roles']);

        $this->assertIsArray($profile['garage']);
        $this->assertSame($garage->getId()->toRfc4122(), $profile['garage']['id']);
        $this->assertSame($garage->getName(), $profile['garage']['name']);
        $this->assertSame($garage->getSubscriptionStatus(), $profile['garage']['subscriptionStatus']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
