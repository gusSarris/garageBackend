<?php

namespace App\Tests;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserSoftDeleteTest extends WebTestCase
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

    private function getJwtToken(User $user, string $password = 'UserPass123!'): string
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
        $data = json_decode($response->getContent(), true);

        return $data['token'] ?? '';
    }

    public function testSoftDeletedUserCannotLogin(): void
    {
        $garage = $this->createGarage('login_test');
        $user = $this->createGarageUser($garage, 'ROLE_MECHANIC', 'ValidPassword123!');

        // Soft-delete user directly in database
        $user->setDeletedAt(new \DateTimeImmutable());
        $user->setIsActive(false);
        $this->entityManager->flush();

        // Attempt login via POST /api/login_check
        $this->client->request(
            'POST',
            '/api/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $user->getEmail(),
                'password' => 'ValidPassword123!',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testSoftDeletedUserExistingJwtRejected(): void
    {
        $garage = $this->createGarage('jwt_test');
        $user = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN', 'ValidPassword123!');

        // 1. Obtain JWT while user is active
        $token = $this->getJwtToken($user, 'ValidPassword123!');
        $this->assertNotEmpty($token);

        // Verify token works on /api/auth/me
        $this->client->request(
            'GET',
            '/api/auth/me',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseStatusCodeSame(200);

        // 2. Soft-delete the user
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $dbUser = $em->find(User::class, $user->getId());
        $dbUser->setDeletedAt(new \DateTimeImmutable());
        $dbUser->setIsActive(false);
        $em->flush();

        // 3. Attempt to use the existing JWT token after deletion
        $this->client->request(
            'GET',
            '/api/auth/me',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        // Because UserChecker is registered on the api firewall, the token is now rejected
        $this->assertResponseStatusCodeSame(401);
    }

    public function testIncludeDeletedQueryParamReturnsArchivedUsers(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $adminToken = $this->getJwtToken($superAdmin, 'AdminPass123!');

        $garage = $this->createGarage('audit_test');
        $activeUser = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $deletedUser = $this->createGarageUser($garage, 'ROLE_MECHANIC');

        // Soft delete one user
        $deletedUser->setDeletedAt(new \DateTimeImmutable());
        $deletedUser->setIsActive(false);
        $this->entityManager->flush();

        $garageId = $garage->getId()->toRfc4122();

        // 1. Standard listing without include_deleted -> returns only active user
        $this->client->request(
            'GET',
            '/api/admin/garages/' . $garageId . '/users',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertResponseStatusCodeSame(200);
        $activeList = json_decode($this->client->getResponse()->getContent(), true);

        $activeIds = array_column($activeList, 'id');
        $this->assertContains($activeUser->getId()->toRfc4122(), $activeIds);
        $this->assertNotContains($deletedUser->getId()->toRfc4122(), $activeIds);

        // 2. Listing with include_deleted=true -> returns both
        $this->client->request(
            'GET',
            '/api/admin/garages/' . $garageId . '/users?include_deleted=true',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertResponseStatusCodeSame(200);
        $allList = json_decode($this->client->getResponse()->getContent(), true);

        $allIds = array_column($allList, 'id');
        $this->assertContains($activeUser->getId()->toRfc4122(), $allIds);
        $this->assertContains($deletedUser->getId()->toRfc4122(), $allIds);

        // Verify deletedAt is present on deleted user and null on active user
        foreach ($allList as $item) {
            if ($item['id'] === $deletedUser->getId()->toRfc4122()) {
                $this->assertNotNull($item['deletedAt']);
            }
            if ($item['id'] === $activeUser->getId()->toRfc4122()) {
                $this->assertNull($item['deletedAt']);
            }
        }
    }

    public function testRecreationOfSameEmailAllowedAfterSoftDelete(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $adminToken = $this->getJwtToken($superAdmin, 'AdminPass123!');

        $garage = $this->createGarage('email_reuse');
        $garageId = $garage->getId()->toRfc4122();

        $unique = bin2hex(random_bytes(4));
        $reusedEmail = 'reuse_' . $unique . '@example.com';

        // 1. Create initial user via API
        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garageId . '/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $reusedEmail,
                'fullName' => 'First Owner Of Email',
                'password' => 'SecurePass123!',
                'role' => 'mechanic',
            ])
        );
        $this->assertResponseStatusCodeSame(201);
        $firstUserData = json_decode($this->client->getResponse()->getContent(), true);
        $firstUserId = $firstUserData['id'];

        // 2. Soft-delete that user
        $this->client->request(
            'DELETE',
            '/api/admin/garages/' . $garageId . '/users/' . $firstUserId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertResponseStatusCodeSame(200);

        // 3. Create a new user with the exact same email
        $this->client->request(
            'POST',
            '/api/admin/garages/' . $garageId . '/users',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => $reusedEmail,
                'fullName' => 'Second Owner Of Email',
                'password' => 'NewSecurePass123!',
                'role' => 'mechanic',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $secondUserData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotSame($firstUserId, $secondUserData['id']);
        $this->assertSame($reusedEmail, $secondUserData['email']);
    }

    public function testInviteGarageWithSoftDeletedEmailAllowed(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $adminToken = $this->getJwtToken($superAdmin, 'AdminPass123!');

        $unique = bin2hex(random_bytes(4));
        $reusedEmail = 'invite_owner_' . $unique . '@example.com';

        // Create an old garage and owner, then soft-delete the owner
        $oldGarage = $this->createGarage('old_workshop');
        $oldOwner = $this->createGarageUser($oldGarage, 'ROLE_GARAGE_ADMIN');
        $oldOwner->setEmail($reusedEmail);
        $oldOwner->setDeletedAt(new \DateTimeImmutable());
        $oldOwner->setIsActive(false);
        $this->entityManager->flush();

        // Onboard a new garage using the soft-deleted owner's email
        $this->client->request(
            'POST',
            '/api/admin/garages',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'name' => 'Brand New Workshop ' . $unique,
                'email' => 'new_garage_' . $unique . '@example.com',
                'phone' => '+30 210 9999999',
                'vatNumber' => 'EL' . rand(100000000, 999999999),
                'adminEmail' => $reusedEmail,
                'adminFullName' => 'Reborn Garage Admin',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('invitation', $response);
        $this->assertArrayHasKey('invitationUrl', $response['invitation']);
        $this->assertSame($reusedEmail, $response['admin']['email']);
    }
}
