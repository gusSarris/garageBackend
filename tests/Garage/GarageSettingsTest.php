<?php

namespace App\Tests\Garage;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GarageSettingsTest extends WebTestCase
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

    private function createMechanic(Garage $garage, string $password = 'AdminPass123!'): User
    {
        $unique = bin2hex(random_bytes(4));

        $user = new User();
        $user->setEmail('mech_' . $unique . '@example.com');
        $user->setFullName('Mechanic ' . $unique);
        $user->setRoles(['ROLE_MECHANIC']);
        $user->setIsActive(true);
        $user->setGarage($garage);
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

    public function testFetchDefaultSettings(): void
    {
        [$admin] = $this->createGarageAdmin();
        $token = $this->getJwtToken($admin);

        $this->client->request(
            'GET',
            '/api/garage/settings',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Reminders
        $this->assertArrayHasKey('reminders', $data);
        $this->assertSame(30, $data['reminders']['kteoDaysBefore']);
        $this->assertSame(12, $data['reminders']['serviceMonthsInterval']);
        $this->assertSame(5000, $data['reminders']['serviceKmInterval']);
        $this->assertTrue($data['reminders']['autoRemindersDefault']);

        // Permissions
        $this->assertArrayHasKey('permissions', $data);
        $this->assertFalse($data['permissions']['mecCanTweakPrice']);

        // Presets
        $this->assertArrayHasKey('presets', $data);
        $this->assertContains('Service', $data['presets']['quickWorkChips']);
        $this->assertContains('Λάδια/Φίλτρο', $data['presets']['quickWorkChips']);
        $this->assertSame([20, 50, 80, 120], $data['presets']['quickPricePresets']);

        // Messaging
        $this->assertArrayHasKey('messaging', $data);
        $this->assertSame('WorkshopHub', $data['messaging']['smsSenderName']);

        // Display
        $this->assertArrayHasKey('display', $data);
        $this->assertTrue($data['display']['darkMode']);
    }

    public function testPartialPatchSettingsByAdmin(): void
    {
        [$admin] = $this->createGarageAdmin();
        $token = $this->getJwtToken($admin);

        $patchPayload = [
            'permissions' => [
                'mecCanTweakPrice' => true,
            ],
            'display' => [
                'darkMode' => false,
            ],
            'messaging' => [
                'smsSenderName' => 'Sarris Moto',
            ],
            'reminders' => [
                'kteoDaysBefore' => 45,
            ],
        ];

        $this->client->request(
            'PATCH',
            '/api/garage/settings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode($patchPayload)
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Check updated fields
        $this->assertTrue($data['permissions']['mecCanTweakPrice']);
        $this->assertFalse($data['display']['darkMode']);
        $this->assertSame('Sarris Moto', $data['messaging']['smsSenderName']);
        $this->assertSame(45, $data['reminders']['kteoDaysBefore']);

        // Check untouched fields remain intact
        $this->assertSame(12, $data['reminders']['serviceMonthsInterval']);
        $this->assertSame(5000, $data['reminders']['serviceKmInterval']);
        $this->assertSame([20, 50, 80, 120], $data['presets']['quickPricePresets']);
        $this->assertContains('Service', $data['presets']['quickWorkChips']);
    }

    public function testMechanicCanReadSettings(): void
    {
        [$admin, $garage] = $this->createGarageAdmin();
        $mechanic = $this->createMechanic($garage);
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'GET',
            '/api/garage/settings',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('permissions', $data);
        $this->assertFalse($data['permissions']['mecCanTweakPrice']);
    }

    public function testMechanicCannotUpdateSettings(): void
    {
        [$admin, $garage] = $this->createGarageAdmin();
        $mechanic = $this->createMechanic($garage);
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'PATCH',
            '/api/garage/settings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'display' => ['darkMode' => false],
            ])
        );

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testValidationRejectsInvalidSettings(): void
    {
        [$admin] = $this->createGarageAdmin();
        $token = $this->getJwtToken($admin);

        // Invalid kteoDaysBefore (< 1), negative price preset, empty smsSenderName
        $this->client->request(
            'PATCH',
            '/api/garage/settings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'reminders' => [
                    'kteoDaysBefore' => 0,
                ],
                'presets' => [
                    'quickPricePresets' => [-10],
                ],
                'messaging' => [
                    'smsSenderName' => '',
                ],
            ])
        );

        $this->assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testCrossTenantIsolation(): void
    {
        [$adminA] = $this->createGarageAdmin();
        [$adminB] = $this->createGarageAdmin();

        $tokenA = $this->getJwtToken($adminA);
        $tokenB = $this->getJwtToken($adminB);

        // Garage A updates sender name
        $this->client->request(
            'PATCH',
            '/api/garage/settings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
            ],
            content: json_encode([
                'messaging' => ['smsSenderName' => 'Garage A Moto'],
            ])
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // Garage B still gets default sender name
        $this->client->request(
            'GET',
            '/api/garage/settings',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenB]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $dataB = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('WorkshopHub', $dataB['messaging']['smsSenderName']);
    }

    public function testProfileEndpointIncludesSettings(): void
    {
        [$admin] = $this->createGarageAdmin();
        $token = $this->getJwtToken($admin);

        $this->client->request(
            'GET',
            '/api/garage',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('settings', $data);
        $this->assertSame('WorkshopHub', $data['settings']['messaging']['smsSenderName']);
        $this->assertTrue($data['settings']['display']['darkMode']);
    }

    public function testUnauthenticatedRejected(): void
    {
        $this->client->request('GET', '/api/garage/settings');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'PATCH',
            '/api/garage/settings',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['display' => ['darkMode' => false]])
        );
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
