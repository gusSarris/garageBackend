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

class WorkOrderManagementTest extends WebTestCase
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

    private function createSuperAdmin(string $password = 'TestPass123!'): User
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

    private function createCustomer(Garage $garage, array $overrides = []): Customer
    {
        $unique = bin2hex(random_bytes(4));

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone($overrides['phone'] ?? '+3069' . rand(10000000, 99999999));
        $customer->setFirstName($overrides['firstName'] ?? 'First ' . $unique);
        $customer->setLastName($overrides['lastName'] ?? 'Last ' . $unique);
        $customer->setCompanyName($overrides['companyName'] ?? null);
        $customer->setEmail($overrides['email'] ?? 'cust_' . $unique . '@example.com');
        $customer->setCity($overrides['city'] ?? 'Athens');
        $customer->setAddress($overrides['address'] ?? 'Test Street 1');
        $customer->setPostalCode($overrides['postalCode'] ?? '12345');
        $customer->setVatNumber($overrides['vatNumber'] ?? null);
        $customer->setTaxOffice($overrides['taxOffice'] ?? null);
        $customer->setSecondaryPhone($overrides['secondaryPhone'] ?? null);
        $customer->setNotes($overrides['notes'] ?? null);

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
        $vehicle->setMake($overrides['make'] ?? 'Toyota');
        $vehicle->setModel($overrides['model'] ?? 'Yaris');
        $vehicle->setVin($overrides['vin'] ?? 'VIN' . $unique . rand(10000000, 99999999));
        $vehicle->setYear($overrides['year'] ?? 2020);
        $vehicle->setMileage($overrides['mileage'] ?? 50000);
        $vehicle->setAllowReminders($overrides['allowReminders'] ?? true);

        if (isset($overrides['nextKteoDate'])) {
            $vehicle->setNextKteoDate(
                $overrides['nextKteoDate'] instanceof \DateTimeImmutable
                    ? $overrides['nextKteoDate']
                    : new \DateTimeImmutable($overrides['nextKteoDate'])
            );
        }

        if (isset($overrides['nextServiceDate'])) {
            $vehicle->setNextServiceDate(
                $overrides['nextServiceDate'] instanceof \DateTimeImmutable
                    ? $overrides['nextServiceDate']
                    : new \DateTimeImmutable($overrides['nextServiceDate'])
            );
        }

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
        $workOrder->setStatus($overrides['status'] ?? 'checked_in');
        $workOrder->setScheduledTime($overrides['scheduledTime'] ?? '10:00');
        $workOrder->setPrice($overrides['price'] ?? '100.00');
        $workOrder->setOdometerKm($overrides['odometerKm'] ?? 50000);
        $workOrder->setNotes($overrides['notes'] ?? null);
        $workOrder->setPartsNotes($overrides['partsNotes'] ?? null);

        if (isset($overrides['checkedInAt'])) {
            $workOrder->setCheckedInAt(
                $overrides['checkedInAt'] instanceof \DateTimeImmutable
                    ? $overrides['checkedInAt']
                    : new \DateTimeImmutable($overrides['checkedInAt'])
            );
        }
        if (isset($overrides['completedAt'])) {
            $workOrder->setCompletedAt(
                $overrides['completedAt'] instanceof \DateTimeImmutable
                    ? $overrides['completedAt']
                    : new \DateTimeImmutable($overrides['completedAt'])
            );
        }
        if (isset($overrides['pickedUpAt'])) {
            $workOrder->setPickedUpAt(
                $overrides['pickedUpAt'] instanceof \DateTimeImmutable
                    ? $overrides['pickedUpAt']
                    : new \DateTimeImmutable($overrides['pickedUpAt'])
            );
        }

        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();

        return $workOrder;
    }

    public function testMechanicCanCreateWorkOrder(): void
    {
        $garage = $this->createGarage('wo_create');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage, ['firstName' => 'Nikos', 'lastName' => 'Papadopoulos']);
        $vehicle = $this->createVehicle($garage, $customer, ['licensePlate' => 'IBZ-1234', 'mileage' => 50000]);
        $token = $this->getJwtToken($mechanic);

        // POST /api/garage/work-orders without explicit customerId (should inherit from vehicle)
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'Annual full service and brake pad replacement',
                'odometerKm' => 53500,
                'price' => '240.00',
                'notes' => 'Brake pads worn down',
                'partsNotes' => 'Bosch pads',
                'scheduledTime' => '09:30',
            ])
        );

        $response = $this->client->getResponse();
        $this->assertSame(201, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['id']);
        $this->assertSame('Annual full service and brake pad replacement', $data['description']);
        $this->assertSame('checked_in', $data['status']);
        $this->assertSame('240.00', $data['price']);
        $this->assertSame(53500, $data['odometerKm']);
        $this->assertSame('09:30', $data['scheduledTime']);
        $this->assertSame('Brake pads worn down', $data['notes']);
        $this->assertSame('Bosch pads', $data['partsNotes']);
        $this->assertNotNull($data['checkedInAt']);
        $this->assertNull($data['completedAt']);
        $this->assertNull($data['pickedUpAt']);

        // Check customer and vehicle associations
        $this->assertSame($customer->getId()->toRfc4122(), $data['customer']['id']);
        $this->assertSame('Nikos Papadopoulos', $data['customer']['name']);
        $this->assertSame($vehicle->getId()->toRfc4122(), $data['vehicle']['id']);
        $this->assertSame('IBZ-1234', $data['vehicle']['licensePlate']);

        // Verify vehicle mileage updated
        $this->entityManager->clear();
        $refreshedVehicle = $this->entityManager->find(Vehicle::class, $vehicle->getId());
        $this->assertSame(53500, $refreshedVehicle->getMileage());
    }

    public function testMechanicCanListWorkOrders(): void
    {
        $garageA = $this->createGarage('wo_list_a');
        $mechanicA = $this->createGarageUser($garageA, 'ROLE_MECHANIC');
        $customerA = $this->createCustomer($garageA);
        $vehicleA1 = $this->createVehicle($garageA, $customerA);
        $vehicleA2 = $this->createVehicle($garageA, $customerA);
        $woA1 = $this->createWorkOrder($garageA, $customerA, $vehicleA1, ['description' => 'Garage A Job 1']);
        $woA2 = $this->createWorkOrder($garageA, $customerA, $vehicleA2, ['description' => 'Garage A Job 2']);

        $garageB = $this->createGarage('wo_list_b');
        $customerB = $this->createCustomer($garageB);
        $vehicleB = $this->createVehicle($garageB, $customerB);
        $this->createWorkOrder($garageB, $customerB, $vehicleB, ['description' => 'Garage B Job 1']);

        $tokenA = $this->getJwtToken($mechanicA);

        $this->client->request(
            'GET',
            '/api/garage/work-orders',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA]
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);

        $ids = array_column($data, 'id');
        $this->assertContains($woA1->getId()->toRfc4122(), $ids);
        $this->assertContains($woA2->getId()->toRfc4122(), $ids);
    }

    public function testFilterWorkOrdersByStatus(): void
    {
        $garage = $this->createGarage('wo_filter_status');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle1 = $this->createVehicle($garage, $customer);
        $vehicle2 = $this->createVehicle($garage, $customer);
        $vehicle3 = $this->createVehicle($garage, $customer);

        $this->createWorkOrder($garage, $customer, $vehicle1, ['status' => 'checked_in', 'description' => 'Check in task']);
        $woInProgress = $this->createWorkOrder($garage, $customer, $vehicle2, ['status' => 'in_progress', 'description' => 'Active repair']);
        $this->createWorkOrder($garage, $customer, $vehicle3, ['status' => 'completed', 'description' => 'Done task']);

        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'GET',
            '/api/garage/work-orders?status=in_progress',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame($woInProgress->getId()->toRfc4122(), $data[0]['id']);
        $this->assertSame('in_progress', $data[0]['status']);
    }

    public function testFilterWorkOrdersByVehicleAndCustomer(): void
    {
        $garage = $this->createGarage('wo_filter_rel');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');

        $customer1 = $this->createCustomer($garage, ['firstName' => 'Customer', 'lastName' => 'One']);
        $vehicle1 = $this->createVehicle($garage, $customer1);
        $wo1 = $this->createWorkOrder($garage, $customer1, $vehicle1, ['description' => 'Job 1']);

        $customer2 = $this->createCustomer($garage, ['firstName' => 'Customer', 'lastName' => 'Two']);
        $vehicle2 = $this->createVehicle($garage, $customer2);
        $wo2 = $this->createWorkOrder($garage, $customer2, $vehicle2, ['description' => 'Job 2']);

        $token = $this->getJwtToken($mechanic);

        // Filter by customer_id
        $this->client->request(
            'GET',
            '/api/garage/work-orders?customer_id=' . $customer1->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $dataCust = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $dataCust);
        $this->assertSame($wo1->getId()->toRfc4122(), $dataCust[0]['id']);

        // Filter by vehicle_id
        $this->client->request(
            'GET',
            '/api/garage/work-orders?vehicle_id=' . $vehicle2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $dataVeh = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $dataVeh);
        $this->assertSame($wo2->getId()->toRfc4122(), $dataVeh[0]['id']);
    }

    public function testSearchWorkOrders(): void
    {
        $garage = $this->createGarage('wo_search');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');

        $customer1 = $this->createCustomer($garage, ['phone' => '+306911223344']);
        $vehicle1 = $this->createVehicle($garage, $customer1, ['licensePlate' => 'XYZ-7788']);
        $wo1 = $this->createWorkOrder($garage, $customer1, $vehicle1, ['description' => 'Transmission overhaul']);

        $customer2 = $this->createCustomer($garage, ['firstName' => 'Dimitris', 'lastName' => 'Oikonomou']);
        $vehicle2 = $this->createVehicle($garage, $customer2, ['licensePlate' => 'KMN-1122']);
        $wo2 = $this->createWorkOrder($garage, $customer2, $vehicle2, [
            'description' => 'Suspension repair',
            'notes' => 'Rear left shock absorber leakage',
        ]);

        $token = $this->getJwtToken($mechanic);

        // Search by description keyword
        $this->client->request(
            'GET',
            '/api/garage/work-orders?query=transmission',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $dataDesc = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $dataDesc);
        $this->assertSame($wo1->getId()->toRfc4122(), $dataDesc[0]['id']);

        // Search by notes
        $this->client->request(
            'GET',
            '/api/garage/work-orders?query=shock',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $dataNotes = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $dataNotes);
        $this->assertSame($wo2->getId()->toRfc4122(), $dataNotes[0]['id']);

        // Search by license plate
        $this->client->request(
            'GET',
            '/api/garage/work-orders?query=xyz-7788',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $dataPlate = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $dataPlate);
        $this->assertSame($wo1->getId()->toRfc4122(), $dataPlate[0]['id']);

        // Search by customer name
        $this->client->request(
            'GET',
            '/api/garage/work-orders?query=oikonomou',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $dataName = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $dataName);
        $this->assertSame($wo2->getId()->toRfc4122(), $dataName[0]['id']);
    }

    public function testAdvanceWorkOrderStatusToCompleted(): void
    {
        $garage = $this->createGarage('wo_advance_comp');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer, ['mileage' => 45000]);
        $wo = $this->createWorkOrder($garage, $customer, $vehicle, [
            'status' => 'in_progress',
            'date' => '2026-09-21',
            'odometerKm' => 49000,
        ]);

        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $wo->getId()->toRfc4122(),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'status' => 'completed',
                'price' => '320.00',
                'partsNotes' => 'Spark plugs replaced',
            ])
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('completed', $data['status']);
        $this->assertSame('320.00', $data['price']);
        $this->assertSame('Spark plugs replaced', $data['partsNotes']);
        $this->assertNotNull($data['completedAt']);

        // Assert vehicle service history updated
        $this->entityManager->clear();
        $refreshedVehicle = $this->entityManager->find(Vehicle::class, $vehicle->getId());
        $this->assertSame('2026-09-21', $refreshedVehicle->getLastServiceDate()->format('Y-m-d'));
        $this->assertSame(49000, $refreshedVehicle->getLastServiceMileage());
        $this->assertSame(49000, $refreshedVehicle->getMileage());
    }

    public function testAdvanceWorkOrderStatusToDelivered(): void
    {
        $garage = $this->createGarage('wo_advance_del');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $wo = $this->createWorkOrder($garage, $customer, $vehicle, ['status' => 'completed']);

        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $wo->getId()->toRfc4122(),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'status' => 'delivered',
            ])
        );

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('delivered', $data['status']);
        $this->assertNotNull($data['pickedUpAt']);
    }

    public function testCrossTenantWorkOrderIsolation(): void
    {
        $garageA = $this->createGarage('wo_iso_a');
        $mechanicA = $this->createGarageUser($garageA, 'ROLE_MECHANIC');

        $garageB = $this->createGarage('wo_iso_b');
        $customerB = $this->createCustomer($garageB);
        $vehicleB = $this->createVehicle($garageB, $customerB);
        $woB = $this->createWorkOrder($garageB, $customerB, $vehicleB, ['description' => 'Target repair']);

        $tokenA = $this->getJwtToken($mechanicA);
        $targetId = $woB->getId()->toRfc4122();

        // GET B's work order with A's token
        $this->client->request(
            'GET',
            '/api/garage/work-orders/' . $targetId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // PATCH B's work order with A's token
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $targetId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
            ],
            content: json_encode(['description' => 'Hacked description'])
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // DELETE B's work order with A's token
        $this->client->request(
            'DELETE',
            '/api/garage/work-orders/' . $targetId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Verify entity still exists in B
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(WorkOrder::class, $woB->getId()));
    }

    public function testCreateWorkOrderRejectsCrossTenantVehicle(): void
    {
        $garageA = $this->createGarage('wo_cross_veh_a');
        $mechanicA = $this->createGarageUser($garageA, 'ROLE_MECHANIC');
        $customerA = $this->createCustomer($garageA);
        $vehicleA = $this->createVehicle($garageA, $customerA);

        $garageB = $this->createGarage('wo_cross_veh_b');
        $customerB = $this->createCustomer($garageB);
        $vehicleB = $this->createVehicle($garageB, $customerB);

        $tokenA = $this->getJwtToken($mechanicA);

        // Mechanic A attempts to create work order with Vehicle B
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
            ],
            content: json_encode([
                'vehicleId' => $vehicleB->getId()->toRfc4122(),
                'description' => 'Cross tenant attempt',
            ])
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Mechanic A attempts to create work order with Vehicle A but Customer B
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
            ],
            content: json_encode([
                'vehicleId' => $vehicleA->getId()->toRfc4122(),
                'customerId' => $customerB->getId()->toRfc4122(),
                'description' => 'Cross customer attempt',
            ])
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testDeleteWorkOrder(): void
    {
        $garage = $this->createGarage('wo_delete');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $wo = $this->createWorkOrder($garage, $customer, $vehicle);

        $token = $this->getJwtToken($mechanic);
        $woId = $wo->getId()->toRfc4122();

        $this->client->request(
            'DELETE',
            '/api/garage/work-orders/' . $woId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Work order successfully deleted', $data['message']);

        // Assert 404 on subsequent get
        $this->client->request(
            'GET',
            '/api/garage/work-orders/' . $woId,
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Assert database removal
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(WorkOrder::class, $wo->getId()));
    }

    public function testSuperAdminWithoutGarageReturnsBadRequest(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        $this->client->request(
            'GET',
            '/api/garage/work-orders',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => '019213ef-0000-7000-8000-000000000000',
                'description' => 'Test repair job',
            ])
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedForbidden(): void
    {
        $this->client->request('GET', '/api/garage/work-orders');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'vehicleId' => '019213ef-0000-7000-8000-000000000000',
                'description' => 'Test repair job',
            ])
        );
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateWorkOrderValidationErrors(): void
    {
        $garage = $this->createGarage('wo_val_err');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);

        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => 'not-a-uuid',
                'description' => 'no', // min 3
                'price' => 'invalid-price',
                'status' => 'unknown_lifecycle_status',
            ])
        );

        $this->assertSame(422, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateWorkOrderRejectsDuplicateActiveVehicleWithConflict409(): void
    {
        $garage = $this->createGarage('wo_dup_reject');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $token = $this->getJwtToken($mechanic);

        // 1. Create initial active work order
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'Initial active repair',
                'status' => 'checked_in',
            ])
        );

        $res1 = $this->client->getResponse();
        $this->assertSame(201, $res1->getStatusCode());
        $data1 = json_decode($res1->getContent(), true);
        $initialWorkOrderId = $data1['id'];

        // 2. Attempt duplicate work order for same vehicle
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'Second repair attempt while first is active',
                'status' => 'checked_in',
            ])
        );

        $res2 = $this->client->getResponse();
        $this->assertSame(409, $res2->getStatusCode(), 'Expected 409 Conflict for duplicate active vehicle work order');
        $data2 = json_decode($res2->getContent(), true);

        $this->assertSame('VEHICLE_ALREADY_ACTIVE', $data2['code']);
        $this->assertStringContainsString('Το όχημα βρίσκεται ήδη στο συνεργείο', $data2['error']);
        $this->assertNotNull($data2['activeWorkOrder']);
        $this->assertSame($initialWorkOrderId, $data2['activeWorkOrder']['id']);
        $this->assertSame('checked_in', $data2['activeWorkOrder']['status']);
        $this->assertSame('Initial active repair', $data2['activeWorkOrder']['description']);
    }

    public function testCreateWorkOrderRejectsWhenVehicleHasScheduledAppointment(): void
    {
        $garage = $this->createGarage('wo_sched_reject');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $token = $this->getJwtToken($mechanic);

        // 1. Create scheduled appointment work order
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'Scheduled queue service',
                'status' => 'scheduled',
                'scheduledTime' => '11:00',
            ])
        );

        $res1 = $this->client->getResponse();
        $this->assertSame(201, $res1->getStatusCode());
        $data1 = json_decode($res1->getContent(), true);
        $scheduledId = $data1['id'];

        // 2. Attempt another work order
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'Check in while already scheduled',
                'status' => 'checked_in',
            ])
        );

        $res2 = $this->client->getResponse();
        $this->assertSame(409, $res2->getStatusCode());
        $data2 = json_decode($res2->getContent(), true);

        $this->assertSame('VEHICLE_ALREADY_ACTIVE', $data2['code']);
        $this->assertSame($scheduledId, $data2['activeWorkOrder']['id']);
        $this->assertSame('scheduled', $data2['activeWorkOrder']['status']);
    }

    public function testCreateWorkOrderAllowsAfterDeliveredOrCancelled(): void
    {
        $garage = $this->createGarage('wo_deliv_allow');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $token = $this->getJwtToken($mechanic);

        // 1. Create delivered work order (vehicle already picked up and archived)
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'Past delivered work order',
                'status' => 'delivered',
            ])
        );
        $this->assertSame(201, $this->client->getResponse()->getStatusCode());

        // 2. A new work order must be allowed
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'New visit after previous was delivered',
                'status' => 'checked_in',
            ])
        );
        $this->assertSame(201, $this->client->getResponse()->getStatusCode());

        // 3. Mark the second work order as cancelled
        $data2 = json_decode($this->client->getResponse()->getContent(), true);
        $secondId = $data2['id'];

        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $secondId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'status' => 'cancelled',
            ])
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // 4. A third work order must be allowed after cancellation
        $this->client->request(
            'POST',
            '/api/garage/work-orders',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'vehicleId' => $vehicle->getId()->toRfc4122(),
                'description' => 'New visit after previous was cancelled',
                'status' => 'checked_in',
            ])
        );
        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
    }

    public function testMechanicCannotDeleteHistoricalWorkOrderButAdminCan(): void
    {
        $garage = $this->createGarage('hist_del_wo');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $vehicle2 = $this->createVehicle($garage, $customer);

        $deliveredWo = $this->createWorkOrder($garage, $customer, $vehicle, [
            'status' => 'delivered',
            'pickedUpAt' => new \DateTimeImmutable('-5 days'),
        ]);

        $pickedUpWo = $this->createWorkOrder($garage, $customer, $vehicle2, [
            'status' => 'completed',
            'pickedUpAt' => new \DateTimeImmutable('-1 day'),
        ]);

        $mechanicToken = $this->getJwtToken($mechanic);
        $adminToken = $this->getJwtToken($admin);

        // 1. Mechanic attempts to delete delivered work order -> 403 Forbidden
        $this->client->request(
            'DELETE',
            '/api/garage/work-orders/' . $deliveredWo->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken]
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $res = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('HISTORICAL_REPAIR_DELETE_FORBIDDEN', $res['code']);
        $this->assertStringContainsString('ιστορικές επισκευές', $res['error']);

        // Verify entity still exists in DB
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(WorkOrder::class, $deliveredWo->getId()));

        // 2. Work order with status != delivered but pickedUpAt != null is also historical -> 403 Forbidden for mechanic
        $this->client->request(
            'DELETE',
            '/api/garage/work-orders/' . $pickedUpWo->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken]
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $resPickedUp = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('HISTORICAL_REPAIR_DELETE_FORBIDDEN', $resPickedUp['code']);

        // 3. Admin deletes delivered work order -> 200 OK
        $this->client->request(
            'DELETE',
            '/api/garage/work-orders/' . $deliveredWo->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $adminRes = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Work order successfully deleted', $adminRes['message']);

        // Verify entity removed from DB
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(WorkOrder::class, $deliveredWo->getId()));
    }

    public function testMechanicCannotUpdateCriticalFieldsOnDeliveredWorkOrderButAdminCan(): void
    {
        $garage = $this->createGarage('deliv_upd_lock');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);

        $deliveredWo = $this->createWorkOrder($garage, $customer, $vehicle, [
            'status' => 'delivered',
            'price' => '150.00',
            'odometerKm' => 45000,
            'description' => 'Original delivered service',
            'pickedUpAt' => new \DateTimeImmutable('-2 days'),
        ]);

        $mechanicToken = $this->getJwtToken($mechanic);
        $adminToken = $this->getJwtToken($admin);
        $woId = $deliveredWo->getId()->toRfc4122();

        // 1. Mechanic attempts to mutate price -> 403 Forbidden
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken,
            ],
            content: json_encode(['price' => '250.00'])
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $res = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('DELIVERED_REPAIR_UPDATE_LOCKED', $res['code']);
        $this->assertStringContainsString('παραδοθείσας επισκευής', $res['error']);

        // 2. Mechanic attempts to mutate odometerKm -> 403 Forbidden
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken,
            ],
            content: json_encode(['odometerKm' => 99000])
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // 3. Mechanic attempts to mutate status -> 403 Forbidden
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken,
            ],
            content: json_encode(['status' => 'in_progress'])
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // 4. Mechanic attempts to mutate date -> 403 Forbidden
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken,
            ],
            content: json_encode(['date' => '2026-01-01'])
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // 5. Mechanic attempts to mutate description -> 403 Forbidden
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken,
            ],
            content: json_encode(['description' => 'Hacked description text'])
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // Verify entity in DB was untouched
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(WorkOrder::class, $deliveredWo->getId());
        $this->assertSame('150.00', $reloaded->getPrice());
        $this->assertSame(45000, $reloaded->getOdometerKm());
        $this->assertSame('delivered', $reloaded->getStatus());

        // 6. Mechanic can still update non-critical field (notes) -> 200 OK
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken,
            ],
            content: json_encode(['notes' => 'Customer called to thank for service'])
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // 7. Admin updates price and odometerKm -> 200 OK
        $this->client->request(
            'PATCH',
            '/api/garage/work-orders/' . $woId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            ],
            content: json_encode([
                'price' => '200.00',
                'odometerKm' => 46000,
            ])
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $adminData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('200.00', $adminData['price']);
        $this->assertSame(46000, $adminData['odometerKm']);

        // Verify DB updated by Admin
        $this->entityManager->clear();
        $adminReloaded = $this->entityManager->find(WorkOrder::class, $deliveredWo->getId());
        $this->assertSame('200.00', $adminReloaded->getPrice());
        $this->assertSame(46000, $adminReloaded->getOdometerKm());
    }
}
