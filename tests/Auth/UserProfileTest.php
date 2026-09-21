<?php

namespace App\Tests\Auth;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserProfileTest extends WebTestCase
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
        $garage->setName('Auto Moto Service Sarris ' . $unique);
        $garage->setEmail('contact_' . $unique . '@example.com');
        $garage->setPhone('+30 210 1234567');
        $garage->setVatNumber('EL' . random_int(100000000, 999999999));
        $garage->setTaxOffice('DOY Glyfadas');
        $garage->setAddress('Leoforos Vouliagmenis 100');
        $garage->setCity('Athens');
        $garage->setPostalCode('16674');
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);
        $this->entityManager->persist($garage);

        $user = new User();
        $user->setEmail('admin_' . $unique . '@example.com');
        $user->setFullName('Giannis Sarris ' . $unique);
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
        $data = json_decode((string) $response->getContent(), true);

        return $data['token'] ?? '';
    }

    public function testAuthenticatedUserCanUpdateFullName(): void
    {
        [$user] = $this->createMechanic();
        $token = $this->getJwtToken($user);

        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'fullName' => 'Nikos Papadopoulos',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Nikos Papadopoulos', $data['fullName']);
        $this->assertSame('Nikos', $data['firstName']);
        $this->assertSame('Papadopoulos', $data['lastName']);
        $this->assertSame($user->getEmail(), $data['email']);

        // Verify database persistence
        $this->entityManager->clear();
        $refreshed = $this->entityManager->find(User::class, $user->getId());
        $this->assertNotNull($refreshed);
        $this->assertSame('Nikos Papadopoulos', $refreshed->getFullName());
    }

    public function testAuthenticatedUserCanUpdateEmail(): void
    {
        [$user] = $this->createGarageAdmin('AdminPass123!');
        $token = $this->getJwtToken($user, 'AdminPass123!');

        $newEmail = 'updated.owner.' . bin2hex(random_bytes(3)) . '@example.com';

        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $newEmail,
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame($newEmail, $data['email']);

        // Verify database persistence
        $this->entityManager->clear();
        $refreshed = $this->entityManager->find(User::class, $user->getId());
        $this->assertNotNull($refreshed);
        $this->assertSame($newEmail, $refreshed->getEmail());

        // Verify login works with new email
        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $newEmail,
                'password' => 'AdminPass123!',
            ])
        );
        $this->assertResponseIsSuccessful();
        $loginData = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $loginData);
    }

    public function testUpdateEmailWithSameEmailSucceeds(): void
    {
        [$user] = $this->createGarageAdmin();
        $token = $this->getJwtToken($user);

        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $user->getEmail(),
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testUpdateEmailFailsOnDuplicateEmail(): void
    {
        [$user1] = $this->createGarageAdmin();
        [$user2] = $this->createGarageAdmin();
        $token1 = $this->getJwtToken($user1);

        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token1,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $user2->getEmail(),
            ])
        );

        $this->assertResponseStatusCodeSame(409);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('already exists', $data['error']);
    }

    public function testUpdateProfileIgnoresRoleEscalationAttempts(): void
    {
        [$user] = $this->createMechanic();
        $token = $this->getJwtToken($user);

        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'fullName' => 'Escalated Mechanic',
                'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_GARAGE_ADMIN'],
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertNotContains('ROLE_SUPER_ADMIN', $data['roles']);
        $this->assertContains('ROLE_MECHANIC', $data['roles']);

        // Verify in DB
        $this->entityManager->clear();
        $refreshed = $this->entityManager->find(User::class, $user->getId());
        $this->assertNotNull($refreshed);
        $this->assertFalse($refreshed->isSuperAdmin());
        $this->assertNotContains('ROLE_SUPER_ADMIN', $refreshed->getRoles());
    }

    public function testUpdateProfileValidationFailures(): void
    {
        [$user] = $this->createMechanic();
        $token = $this->getJwtToken($user);

        // Name too short (< 2 characters)
        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'fullName' => 'X',
            ])
        );
        $this->assertResponseStatusCodeSame(422);

        // Invalid email format
        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => 'not-a-valid-email',
            ])
        );
        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordSuccess(): void
    {
        [$user] = $this->createMechanic('OldPassword123!');
        $token = $this->getJwtToken($user, 'OldPassword123!');

        $this->client->request(
            'POST',
            '/api/auth/change-password',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'currentPassword' => 'OldPassword123!',
                'newPassword' => 'NewStrongPass456!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Password successfully updated', $data['message']);

        // Login with old password must fail
        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'OldPassword123!',
            ])
        );
        $this->assertResponseStatusCodeSame(401);

        // Login with new password must succeed
        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'NewStrongPass456!',
            ])
        );
        $this->assertResponseIsSuccessful();
        $loginData = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $loginData);
    }

    public function testChangePasswordFailsOnIncorrectCurrentPassword(): void
    {
        [$user] = $this->createMechanic('CorrectPass123!');
        $token = $this->getJwtToken($user, 'CorrectPass123!');

        $this->client->request(
            'POST',
            '/api/auth/change-password',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'currentPassword' => 'WrongPass123!',
                'newPassword' => 'NewValidPass456!',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Invalid current password', $data['error']);
    }

    public function testChangePasswordEnforcesPasswordPolicy(): void
    {
        [$user] = $this->createMechanic('AdminPass123!');
        $token = $this->getJwtToken($user, 'AdminPass123!');

        // Short password (< 10 chars)
        $this->client->request(
            'POST',
            '/api/auth/change-password',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'currentPassword' => 'AdminPass123!',
                'newPassword' => 'short',
            ])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordRejectsIdenticalPassword(): void
    {
        [$user] = $this->createMechanic('AdminPass123!');
        $token = $this->getJwtToken($user, 'AdminPass123!');

        $this->client->request(
            'POST',
            '/api/auth/change-password',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'currentPassword' => 'AdminPass123!',
                'newPassword' => 'AdminPass123!',
            ])
        );

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('New password cannot be identical to current password.', $data['error']);
    }

    public function testSuperAdminCanUpdateProfileAndChangePassword(): void
    {
        $superAdmin = $this->createSuperAdmin('SuperAdminPass123!');
        $token = $this->getJwtToken($superAdmin, 'SuperAdminPass123!');

        // Update profile
        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'fullName' => 'Updated Super Admin',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('Updated Super Admin', $data['fullName']);
        $this->assertNull($data['garage']);

        // Change password
        $this->client->request(
            'POST',
            '/api/auth/change-password',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'currentPassword' => 'SuperAdminPass123!',
                'newPassword' => 'NewSuperAdminPass456!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);

        // Login with new password
        $this->client->request(
            'POST',
            '/api/login_check',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $superAdmin->getEmail(),
                'password' => 'NewSuperAdminPass456!',
            ])
        );
        $this->assertResponseIsSuccessful();
        $loginData = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $loginData);
    }

    public function testUnauthenticatedRequestsRejected(): void
    {
        $this->client->request(
            'PATCH',
            '/api/auth/me',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'fullName' => 'Anonymous User',
            ])
        );
        $this->assertResponseStatusCodeSame(401);

        $this->client->request(
            'POST',
            '/api/auth/change-password',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'currentPassword' => 'SomePassword123!',
                'newPassword' => 'AnotherPassword456!',
            ])
        );
        $this->assertResponseStatusCodeSame(401);
    }
}
