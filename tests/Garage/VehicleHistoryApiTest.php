<?php

namespace App\Tests\Garage;

use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VehicleHistoryApiTest extends WebTestCase
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
        $garage->setPhone('+30 210 ' . rand(1000000, 9999999));
        $garage->setVatNumber('EL' . rand(100000000, 999999999));
        $garage->setTaxOffice('DOY ' . $unique);
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);

        $this->entityManager->persist($garage);
        $this->entityManager->flush();

        return $garage;
    }

    private function createGarageUser(Garage $garage, string $role = 'ROLE_MECHANIC', string $password = 'TestPass123!'): User
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
        $this->assertSame(200, $response->getStatusCode(), 'Failed to authenticate: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);

        return $data['token'];
    }

    private function createCustomer(Garage $garage): Customer
    {
        $unique = bin2hex(random_bytes(4));

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('+3069' . rand(10000000, 99999999));
        $customer->setFirstName('First ' . $unique);
        $customer->setLastName('Last ' . $unique);
        $customer->setEmail('cust_' . $unique . '@example.com');
        $customer->setCity('Athens');
        $customer->setAddress('Test Street 1');
        $customer->setPostalCode('12345');

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    private function createVehicle(Garage $garage, Customer $customer, array $overrides = []): Vehicle
    {
        $unique = strtoupper(bin2hex(random_bytes(2)));

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate($overrides['licensePlate'] ?? 'ABC-' . rand(1000, 9999));
        $vehicle->setMake($overrides['make'] ?? 'Yamaha');
        $vehicle->setModel($overrides['model'] ?? 'TMAX');
        $vehicle->setVin($overrides['vin'] ?? 'VIN' . $unique . rand(10000000, 99999999));
        $vehicle->setYear($overrides['year'] ?? 2022);
        $vehicle->setMileage($overrides['mileage'] ?? 20000);
        $vehicle->setAllowReminders($overrides['allowReminders'] ?? true);

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        return $vehicle;
    }

    private function createWorkOrder(Garage $garage, Customer $customer, Vehicle $vehicle, array $overrides = []): WorkOrder
    {
        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDescription($overrides['description'] ?? 'Standard maintenance and diagnostic');
        $workOrder->setDate(
            isset($overrides['date'])
                ? ($overrides['date'] instanceof \DateTimeImmutable ? $overrides['date'] : new \DateTimeImmutable($overrides['date']))
                : new \DateTimeImmutable('today')
        );
        $workOrder->setStatus($overrides['status'] ?? 'delivered');
        $workOrder->setScheduledTime($overrides['scheduledTime'] ?? '10:00');
        $workOrder->setPrice($overrides['price'] ?? '100.00');
        $workOrder->setOdometerKm($overrides['odometerKm'] ?? 20000);
        $workOrder->setNotes($overrides['notes'] ?? null);
        $workOrder->setPartsNotes($overrides['partsNotes'] ?? null);

        if (isset($overrides['checkedInAt'])) {
            $workOrder->setCheckedInAt(
                $overrides['checkedInAt'] instanceof \DateTimeImmutable ? $overrides['checkedInAt'] : new \DateTimeImmutable($overrides['checkedInAt'])
            );
        }
        if (isset($overrides['completedAt'])) {
            $workOrder->setCompletedAt(
                $overrides['completedAt'] instanceof \DateTimeImmutable ? $overrides['completedAt'] : new \DateTimeImmutable($overrides['completedAt'])
            );
        }
        if (isset($overrides['pickedUpAt'])) {
            $workOrder->setPickedUpAt(
                $overrides['pickedUpAt'] instanceof \DateTimeImmutable ? $overrides['pickedUpAt'] : new \DateTimeImmutable($overrides['pickedUpAt'])
            );
        }

        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();

        return $workOrder;
    }

    public function testSuccessfulHistoryRetrievalChronologicalOrder(): void
    {
        $garage = $this->createGarage('hist_chrono');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        // Create 3 work orders on different dates
        $wo1 = $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2024-05-10',
            'status' => 'delivered',
            'description' => 'Oldest service 10k',
            'price' => '80.00',
            'odometerKm' => 10000,
            'notes' => 'Notes 1',
            'partsNotes' => 'Parts 1',
        ]);

        $wo2 = $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2025-01-15',
            'status' => 'completed',
            'description' => 'Middle service 15k',
            'price' => '150.00',
            'odometerKm' => 15000,
            'notes' => 'Notes 2',
        ]);

        $wo3 = $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2025-09-20',
            'status' => 'delivered',
            'description' => 'Newest service 20k',
            'price' => '220.00',
            'odometerKm' => 20500,
            'checkedInAt' => '2025-09-20T08:30:00+03:00',
            'completedAt' => '2025-09-20T17:00:00+03:00',
            'pickedUpAt' => '2025-09-21T11:00:00+03:00',
        ]);

        $vehicleId = $vehicle->getId()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(3, $data);

        // Verify newest first (2025-09-20, then 2025-01-15, then 2024-05-10)
        $this->assertSame($wo3->getId()->toRfc4122(), $data[0]['id']);
        $this->assertSame('2025-09-20', $data[0]['date']);
        $this->assertSame('delivered', $data[0]['status']);
        $this->assertSame('Παραδόθηκε', $data[0]['statusLabel']);
        $this->assertSame('Newest service 20k', $data[0]['description']);
        $this->assertSame('220.00', $data[0]['price']);
        $this->assertSame(20500, $data[0]['odometerKm']);
        $this->assertNotNull($data[0]['checkedInAt']);
        $this->assertNotNull($data[0]['completedAt']);
        $this->assertNotNull($data[0]['pickedUpAt']);
        $this->assertNotNull($data[0]['createdAt']);

        $this->assertSame($wo2->getId()->toRfc4122(), $data[1]['id']);
        $this->assertSame('2025-01-15', $data[1]['date']);
        $this->assertSame('completed', $data[1]['status']);
        $this->assertSame('Ολοκληρώθηκε', $data[1]['statusLabel']);

        $this->assertSame($wo1->getId()->toRfc4122(), $data[2]['id']);
        $this->assertSame('2024-05-10', $data[2]['date']);
        $this->assertSame('delivered', $data[2]['status']);
        $this->assertSame('Παραδόθηκε', $data[2]['statusLabel']);
    }

    public function testExcludeActiveFilter(): void
    {
        $garage = $this->createGarage('hist_excl');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        // Create 1 delivered, 1 completed (picked up), 1 in_progress (active)
        $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2025-01-01',
            'status' => 'delivered',
            'description' => 'Delivered repair',
        ]);
        $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2025-02-01',
            'status' => 'completed',
            'description' => 'Completed repair',
            'pickedUpAt' => new \DateTimeImmutable('2025-02-02'),
        ]);
        $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2025-03-01',
            'status' => 'in_progress',
            'description' => 'In progress repair',
        ]);

        $vehicleId = $vehicle->getId()->toRfc4122();

        // Without exclude_active (returns all 3)
        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $this->assertCount(3, json_decode($this->client->getResponse()->getContent(), true));

        // With exclude_active=true (returns only delivered and completed = 2)
        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history?exclude_active=true',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );
        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $filteredData = json_decode($response->getContent(), true);
        $this->assertCount(2, $filteredData);
        $statuses = array_column($filteredData, 'status');
        $this->assertContains('delivered', $statuses);
        $this->assertContains('completed', $statuses);
        $this->assertNotContains('in_progress', $statuses);
    }

    public function testLimitParameter(): void
    {
        $garage = $this->createGarage('hist_limit');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        for ($i = 1; $i <= 5; $i++) {
            $this->createWorkOrder($garage, $customer, $vehicle, [
                'date' => '2025-0' . $i . '-01',
                'description' => 'Service ' . $i,
            ]);
        }

        $vehicleId = $vehicle->getId()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history?limit=2',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        // Newest should be month 5 and 4
        $this->assertSame('2025-05-01', $data[0]['date']);
        $this->assertSame('2025-04-01', $data[1]['date']);
    }

    public function testEmptyHistoryForNewVehicle(): void
    {
        $garage = $this->createGarage('hist_empty');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        $vehicleId = $vehicle->getId()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data);
    }

    public function testTenantIsolationCrossTenantVehicleReturns404(): void
    {
        $garageA = $this->createGarage('hist_tenant_a');
        $mechanicA = $this->createGarageUser($garageA, 'ROLE_MECHANIC');
        $tokenA = $this->getJwtToken($mechanicA);

        $garageB = $this->createGarage('hist_tenant_b');
        $customerB = $this->createCustomer($garageB);
        $vehicleB = $this->createVehicle($garageB, $customerB);

        $vehicleBId = $vehicleB->getId()->toRfc4122();

        // Mechanic A attempts to retrieve history of Vehicle B
        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleBId . '/history',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Vehicle not found', $data['error']);
    }

    public function testNonExistentVehicleReturns404(): void
    {
        $garage = $this->createGarage('hist_nonexist');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'GET',
            '/api/garage/vehicles/01923e5a-0000-7000-8000-000000000000/history',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        $garage = $this->createGarage('hist_unauth');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        $vehicleId = $vehicle->getId()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testGarageAdminCanAccessHistory(): void
    {
        $garage = $this->createGarage('hist_admin');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $token = $this->getJwtToken($admin);
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        $this->createWorkOrder($garage, $customer, $vehicle, [
            'date' => '2025-08-10',
            'status' => 'delivered',
            'description' => 'Admin test service',
        ]);

        $vehicleId = $vehicle->getId()->toRfc4122();

        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicleId . '/history',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Admin test service', $data[0]['description']);
    }
}
