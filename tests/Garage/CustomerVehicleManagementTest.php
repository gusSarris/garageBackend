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

class CustomerVehicleManagementTest extends WebTestCase
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
        $workOrder->setDate(new \DateTimeImmutable('today'));
        $workOrder->setStatus($overrides['status'] ?? 'checked_in');
        $workOrder->setScheduledTime($overrides['scheduledTime'] ?? '10:00');
        $workOrder->setPrice($overrides['price'] ?? '100.00');

        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();

        return $workOrder;
    }

    public function testMechanicCanCreateAndListCustomer(): void
    {
        $garage = $this->createGarage('cust_create');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $token = $this->getJwtToken($mechanic);

        // POST /api/garage/customers
        $this->client->request(
            'POST',
            '/api/garage/customers',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'phone' => '+306912345678',
                'firstName' => 'Nikos',
                'lastName' => 'Papadopoulos',
                'email' => 'nikos@example.com',
                'city' => 'Athens',
                'notes' => 'Prefers afternoon calls',
            ])
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $createdData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('+306912345678', $createdData['phone']);
        $this->assertSame('Nikos', $createdData['firstName']);
        $this->assertSame('Papadopoulos', $createdData['lastName']);
        $this->assertSame('nikos@example.com', $createdData['email']);
        $this->assertSame('Athens', $createdData['city']);
        $this->assertSame(0, $createdData['vehiclesCount']);
        $this->assertNotNull($createdData['id']);

        // GET /api/garage/customers
        $this->client->request(
            'GET',
            '/api/garage/customers',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $listData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($listData);
        $ids = array_column($listData, 'id');
        $this->assertContains($createdData['id'], $ids);

        // Query search matching lastName
        $this->client->request(
            'GET',
            '/api/garage/customers?query=papadopoulos',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $searchData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $searchData);
        $this->assertSame($createdData['id'], $searchData[0]['id']);

        // Query search not matching
        $this->client->request(
            'GET',
            '/api/garage/customers?query=NonExistentQueryXYZ',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $emptyData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(0, $emptyData);
    }

    public function testMechanicCanCreateVehicleForCustomer(): void
    {
        $garage = $this->createGarage('veh_create');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage, ['firstName' => 'Eleni', 'lastName' => 'Kosta']);
        $token = $this->getJwtToken($mechanic);

        // POST /api/garage/vehicles
        $this->client->request(
            'POST',
            '/api/garage/vehicles',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'customerId' => $customer->getId()->toRfc4122(),
                'licensePlate' => 'ibz-1234',
                'make' => 'Toyota',
                'model' => 'Yaris',
                'vin' => 'wba1234567890abcd',
                'year' => 2018,
                'fuelType' => 'Hybrid',
                'transmission' => 'Automatic',
                'mileage' => 85000,
                'nextKteoDate' => '2026-10-15',
                'nextServiceDate' => '2026-11-01',
                'allowReminders' => true,
            ])
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $vehData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('IBZ-1234', $vehData['licensePlate']);
        $this->assertSame('WBA1234567890ABCD', $vehData['vin']);
        $this->assertSame('Toyota', $vehData['make']);
        $this->assertSame('Yaris', $vehData['model']);
        $this->assertSame(2018, $vehData['year']);
        $this->assertSame(85000, $vehData['mileage']);
        $this->assertSame('2026-10-15', $vehData['nextKteoDate']);
        $this->assertSame('2026-11-01', $vehData['nextServiceDate']);
        $this->assertTrue($vehData['allowReminders']);
        $this->assertSame($customer->getId()->toRfc4122(), $vehData['customer']['id']);

        // Check Customer details endpoint has this vehicle listed
        $this->client->request(
            'GET',
            '/api/garage/customers/' . $customer->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $custDetail = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $custDetail['vehicles']);
        $this->assertSame('IBZ-1234', $custDetail['vehicles'][0]['licensePlate']);
    }

    public function testVehicleRequiresCustomerInSameGarage(): void
    {
        $garage1 = $this->createGarage('g1_veh');
        $mechanic1 = $this->createGarageUser($garage1, 'ROLE_MECHANIC');
        $token1 = $this->getJwtToken($mechanic1);

        $garage2 = $this->createGarage('g2_cust');
        $customer2 = $this->createCustomer($garage2);

        // Attempt to create vehicle in Garage 1 referencing Customer in Garage 2
        $this->client->request(
            'POST',
            '/api/garage/vehicles',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token1,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'customerId' => $customer2->getId()->toRfc4122(),
                'licensePlate' => 'HCK-9999',
                'make' => 'BMW',
                'model' => '320',
            ])
        );

        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Customer not found', $data['error']);
    }

    public function testCrossTenantCustomerIsolation(): void
    {
        $garage1 = $this->createGarage('t1_cust');
        $mechanic1 = $this->createGarageUser($garage1, 'ROLE_MECHANIC');
        $admin1 = $this->createGarageUser($garage1, 'ROLE_GARAGE_ADMIN');
        $customer1 = $this->createCustomer($garage1);
        $token1 = $this->getJwtToken($mechanic1);
        $adminToken1 = $this->getJwtToken($admin1);

        $garage2 = $this->createGarage('t2_cust');
        $customer2 = $this->createCustomer($garage2);

        // Garage 1 GET customer from Garage 2 -> 404
        $this->client->request(
            'GET',
            '/api/garage/customers/' . $customer2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token1]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Garage 1 PATCH customer from Garage 2 -> 404
        $this->client->request(
            'PATCH',
            '/api/garage/customers/' . $customer2->getId()->toRfc4122(),
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token1,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['city' => 'Hacked City'])
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Garage 1 Admin DELETE customer from Garage 2 -> 404
        $this->client->request(
            'DELETE',
            '/api/garage/customers/' . $customer2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken1]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Garage 1 list does not include customer2
        $this->client->request(
            'GET',
            '/api/garage/customers',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token1]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $list = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($list, 'id');
        $this->assertContains($customer1->getId()->toRfc4122(), $ids);
        $this->assertNotContains($customer2->getId()->toRfc4122(), $ids);
    }

    public function testCrossTenantVehicleIsolation(): void
    {
        $garage1 = $this->createGarage('t1_veh');
        $mechanic1 = $this->createGarageUser($garage1, 'ROLE_MECHANIC');
        $admin1 = $this->createGarageUser($garage1, 'ROLE_GARAGE_ADMIN');
        $customer1 = $this->createCustomer($garage1);
        $vehicle1 = $this->createVehicle($garage1, $customer1);
        $token1 = $this->getJwtToken($mechanic1);
        $adminToken1 = $this->getJwtToken($admin1);

        $garage2 = $this->createGarage('t2_veh');
        $customer2 = $this->createCustomer($garage2);
        $vehicle2 = $this->createVehicle($garage2, $customer2);

        // Garage 1 GET vehicle from Garage 2 -> 404
        $this->client->request(
            'GET',
            '/api/garage/vehicles/' . $vehicle2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token1]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Garage 1 PATCH vehicle from Garage 2 -> 404
        $this->client->request(
            'PATCH',
            '/api/garage/vehicles/' . $vehicle2->getId()->toRfc4122(),
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token1,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['mileage' => 999999])
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Garage 1 Admin DELETE vehicle from Garage 2 -> 404
        $this->client->request(
            'DELETE',
            '/api/garage/vehicles/' . $vehicle2->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken1]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());

        // Garage 1 list does not include vehicle2
        $this->client->request(
            'GET',
            '/api/garage/vehicles',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token1]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $list = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($list, 'id');
        $this->assertContains($vehicle1->getId()->toRfc4122(), $ids);
        $this->assertNotContains($vehicle2->getId()->toRfc4122(), $ids);
    }

    public function testVehicleSearchByLicensePlateAndVin(): void
    {
        $garage = $this->createGarage('veh_search');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $token = $this->getJwtToken($mechanic);

        $vehA = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'ABC-1111',
            'vin' => 'VIN11111111111111',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $vehB = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'XYZ-2222',
            'vin' => 'VIN22222222222222',
            'make' => 'Toyota',
            'model' => 'Corolla',
        ]);

        // Search by plate substring
        $this->client->request(
            'GET',
            '/api/garage/vehicles?query=abc',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($data, 'id');
        $this->assertContains($vehA->getId()->toRfc4122(), $ids);
        $this->assertNotContains($vehB->getId()->toRfc4122(), $ids);

        // Search by vin substring
        $this->client->request(
            'GET',
            '/api/garage/vehicles?query=VIN2222',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($data, 'id');
        $this->assertContains($vehB->getId()->toRfc4122(), $ids);
        $this->assertNotContains($vehA->getId()->toRfc4122(), $ids);

        // Search by model substring
        $this->client->request(
            'GET',
            '/api/garage/vehicles?query=Corolla',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($data, 'id');
        $this->assertContains($vehB->getId()->toRfc4122(), $ids);
        $this->assertNotContains($vehA->getId()->toRfc4122(), $ids);

        // Filter by customer_id
        $this->client->request(
            'GET',
            '/api/garage/vehicles?customer_id=' . $customer->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data);
    }

    public function testVehicleUpcomingKteoFilter(): void
    {
        $garage = $this->createGarage('kteo_filter');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $token = $this->getJwtToken($mechanic);

        $now = new \DateTimeImmutable('today');
        $vehDueSoon = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'KTE-0010',
            'nextKteoDate' => $now->modify('+10 days'),
        ]);

        $vehDueLater = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'KTE-0090',
            'nextKteoDate' => $now->modify('+90 days'),
        ]);

        $this->client->request(
            'GET',
            '/api/garage/vehicles?upcoming_kteo=30',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($data, 'id');

        $this->assertContains($vehDueSoon->getId()->toRfc4122(), $ids);
        $this->assertNotContains($vehDueLater->getId()->toRfc4122(), $ids);
    }

    public function testVehicleUpcomingServiceFilter(): void
    {
        $garage = $this->createGarage('srv_filter');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage);
        $token = $this->getJwtToken($mechanic);

        $now = new \DateTimeImmutable('today');
        $vehDueSoon = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'SRV-0015',
            'nextServiceDate' => $now->modify('+15 days'),
        ]);

        $vehDueLater = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'SRV-0120',
            'nextServiceDate' => $now->modify('+120 days'),
        ]);

        $this->client->request(
            'GET',
            '/api/garage/vehicles?upcoming_service=30',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $ids = array_column($data, 'id');

        $this->assertContains($vehDueSoon->getId()->toRfc4122(), $ids);
        $this->assertNotContains($vehDueLater->getId()->toRfc4122(), $ids);
    }

    public function testUpdateCustomerAndVehicle(): void
    {
        $garage = $this->createGarage('update_test');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $customer = $this->createCustomer($garage, ['city' => 'Athens', 'phone' => '+306911111111']);
        $vehicle = $this->createVehicle($garage, $customer, ['mileage' => 50000]);
        $token = $this->getJwtToken($mechanic);

        // Update customer
        $this->client->request(
            'PATCH',
            '/api/garage/customers/' . $customer->getId()->toRfc4122(),
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'city' => 'Thessaloniki',
                'phone' => '+306999999999',
            ])
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $custData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Thessaloniki', $custData['city']);
        $this->assertSame('+306999999999', $custData['phone']);

        // Update vehicle
        $this->client->request(
            'PATCH',
            '/api/garage/vehicles/' . $vehicle->getId()->toRfc4122(),
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'mileage' => 55000,
                'nextServiceMileage' => 65000,
            ])
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $vehData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(55000, $vehData['mileage']);
        $this->assertSame(65000, $vehData['nextServiceMileage']);
    }

    public function testDeleteVehicleAndCustomer(): void
    {
        $garage = $this->createGarage('delete_test');
        $mechanic = $this->createGarageUser($garage, 'ROLE_MECHANIC');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $mechanicToken = $this->getJwtToken($mechanic);
        $adminToken = $this->getJwtToken($admin);

        // Delete vehicle by mechanic -> 403 Forbidden
        $this->client->request(
            'DELETE',
            '/api/garage/vehicles/' . $vehicle->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken]
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // Verify vehicle still exists in db
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->getRepository(Vehicle::class)->find($vehicle->getId()));

        // Delete vehicle by admin (zero work orders exist) -> 200 OK
        $this->client->request(
            'DELETE',
            '/api/garage/vehicles/' . $vehicle->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Vehicle successfully deleted', $data['message']);

        // Verify vehicle gone from db
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->getRepository(Vehicle::class)->find($vehicle->getId()));
        // Verify customer still exists
        $this->assertNotNull($this->entityManager->getRepository(Customer::class)->find($customer->getId()));

        // Delete customer by mechanic -> 403 Forbidden
        $this->client->request(
            'DELETE',
            '/api/garage/customers/' . $customer->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $mechanicToken]
        );
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        // Verify customer still exists in db
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->getRepository(Customer::class)->find($customer->getId()));

        // Delete customer by admin when zero work orders exist -> 200 OK
        $this->client->request(
            'DELETE',
            '/api/garage/customers/' . $customer->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $custDelData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Customer successfully deleted', $custDelData['message']);

        // Verify customer gone from db
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->getRepository(Customer::class)->find($customer->getId()));
    }

    public function testCannotDeleteVehicleWithWorkOrders(): void
    {
        $garage = $this->createGarage('veh_wo_del');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $workOrder = $this->createWorkOrder($garage, $customer, $vehicle);
        $adminToken = $this->getJwtToken($admin);

        // Attempt deletion by admin
        $this->client->request(
            'DELETE',
            '/api/garage/vehicles/' . $vehicle->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertSame(409, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('VEHICLE_HAS_WORK_ORDERS', $response['code']);
        $this->assertSame(1, $response['workOrderCount']);
        $this->assertStringContainsString('ιστορικό επισκευών', $response['error']);

        // Verify vehicle and work order still exist in DB
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->getRepository(Vehicle::class)->find($vehicle->getId()));
        $this->assertNotNull($this->entityManager->getRepository(WorkOrder::class)->find($workOrder->getId()));
    }

    public function testCannotDeleteCustomerWithWorkOrders(): void
    {
        $garage = $this->createGarage('cust_wo_del');
        $admin = $this->createGarageUser($garage, 'ROLE_GARAGE_ADMIN');
        $customer = $this->createCustomer($garage);
        $vehicle = $this->createVehicle($garage, $customer);
        $workOrder = $this->createWorkOrder($garage, $customer, $vehicle);
        $adminToken = $this->getJwtToken($admin);

        // Attempt deletion by admin
        $this->client->request(
            'DELETE',
            '/api/garage/customers/' . $customer->getId()->toRfc4122(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken]
        );

        $this->assertSame(409, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('CUSTOMER_HAS_WORK_ORDERS', $response['code']);
        $this->assertSame(1, $response['workOrderCount']);
        $this->assertStringContainsString('ιστορικό επισκευών', $response['error']);

        // Verify customer and work order still exist in DB
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->getRepository(Customer::class)->find($customer->getId()));
        $this->assertNotNull($this->entityManager->getRepository(WorkOrder::class)->find($workOrder->getId()));
    }

    public function testSuperAdminWithoutGarageReturnsBadRequest(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $this->getJwtToken($superAdmin);

        // Customers GET without garage
        $this->client->request(
            'GET',
            '/api/garage/customers',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());

        // Customers POST without garage
        $this->client->request(
            'POST',
            '/api/garage/customers',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['phone' => '+306900000000'])
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());

        // Vehicles GET without garage
        $this->client->request(
            'GET',
            '/api/garage/vehicles',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());

        // Vehicles POST without garage
        $this->client->request(
            'POST',
            '/api/garage/vehicles',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'customerId' => '019213ab-0000-7000-8000-000000000000',
                'licensePlate' => 'ABC-1234',
                'make' => 'Toyota',
                'model' => 'Yaris',
            ])
        );
        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedForbidden(): void
    {
        // Customers GET
        $this->client->request('GET', '/api/garage/customers');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        // Customers POST
        $this->client->request(
            'POST',
            '/api/garage/customers',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['phone' => '+306912345678'])
        );
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        // Vehicles GET
        $this->client->request('GET', '/api/garage/vehicles');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());

        // Vehicles POST
        $this->client->request(
            'POST',
            '/api/garage/vehicles',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'customerId' => '019213ab-0000-7000-8000-000000000000',
                'licensePlate' => 'ABC-1234',
                'make' => 'Toyota',
                'model' => 'Yaris',
            ])
        );
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
