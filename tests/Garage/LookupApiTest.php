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

class LookupApiTest extends WebTestCase
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
        $vehicle->setMake($overrides['make'] ?? 'Honda');
        $vehicle->setModel($overrides['model'] ?? 'SH 150i');
        $vehicle->setVin($overrides['vin'] ?? 'VIN' . $unique . rand(10000000, 99999999));
        $vehicle->setYear($overrides['year'] ?? 2022);
        $vehicle->setMileage(20000);
        $vehicle->setAllowReminders(true);

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        return $vehicle;
    }

    public function testLookupRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/garage/lookup?phone=6912345678');
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testLookupRequiresAtLeastOneParameter(): void
    {
        $garage = $this->createGarage('lookup_param');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $this->client->request('GET', '/api/garage/lookup', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertSame(400, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testLookupByPhoneFindsCustomerWithSingleVehicle(): void
    {
        $garage = $this->createGarage('lookup_phone_single');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $customer = $this->createCustomer($garage, [
            'firstName' => 'Μαρία',
            'lastName' => 'Κωνσταντίνου',
            'phone' => '+306945557890',
        ]);

        $vehicle = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'YZN-9034',
            'make' => 'BMW',
            'model' => 'F 900 XR',
            'year' => 2022,
        ]);

        // Search with space-formatted input without +30 prefix
        $this->client->request('GET', '/api/garage/lookup?phone=694%20555%207890', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotNull($data['customer']);
        $this->assertSame('Μαρία Κωνσταντίνου', $data['customer']['name']);
        $this->assertSame('+306945557890', $data['customer']['phone']);

        $this->assertCount(1, $data['vehicles']);
        $this->assertSame('YZN-9034', $data['vehicles'][0]['licensePlate']);
        $this->assertSame('BMW', $data['vehicles'][0]['make']);
        $this->assertSame('F 900 XR', $data['vehicles'][0]['model']);
    }

    public function testLookupByPhoneFindsCustomerWithMultipleVehicles(): void
    {
        $garage = $this->createGarage('lookup_phone_multi');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $customer = $this->createCustomer($garage, [
            'firstName' => 'Γιώργος',
            'lastName' => 'Παπαδόπουλος',
            'phone' => '+306912345678',
        ]);

        $v1 = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'IKA-4821',
            'make' => 'Yamaha',
            'model' => 'TMAX 560',
        ]);

        $v2 = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'IKA-9920',
            'make' => 'Honda',
            'model' => 'SH 150i',
        ]);

        $this->client->request('GET', '/api/garage/lookup?phone=%2B306912345678', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotNull($data['customer']);
        $this->assertSame('Γιώργος Παπαδόπουλος', $data['customer']['name']);
        $this->assertCount(2, $data['vehicles']);

        $plates = array_column($data['vehicles'], 'licensePlate');
        $this->assertContains('IKA-4821', $plates);
        $this->assertContains('IKA-9920', $plates);
    }

    public function testLookupByPhoneReturnsEmptyWhenNotFound(): void
    {
        $garage = $this->createGarage('lookup_phone_none');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $this->client->request('GET', '/api/garage/lookup?phone=6900000000', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNull($data['customer']);
        $this->assertSame([], $data['vehicles']);
    }

    public function testLookupByPlateFindsVehicleAndCustomer(): void
    {
        $garage = $this->createGarage('lookup_plate_found');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $customer = $this->createCustomer($garage, [
            'firstName' => 'Νίκος',
            'lastName' => 'Γεωργίου',
            'phone' => '+306978901234',
        ]);

        $vehicle = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'KOH-7710',
            'make' => 'SYM',
            'model' => 'Symphony ST 200',
        ]);

        // Search with lowercase and no hyphens
        $this->client->request('GET', '/api/garage/lookup?plate=koh7710', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotNull($data['vehicle']);
        $this->assertSame('KOH-7710', $data['vehicle']['licensePlate']);
        $this->assertSame('SYM', $data['vehicle']['make']);
        $this->assertSame('Symphony ST 200', $data['vehicle']['model']);

        $this->assertNotNull($data['customer']);
        $this->assertSame('Νίκος Γεωργίου', $data['customer']['name']);
        $this->assertSame('+306978901234', $data['customer']['phone']);
    }

    public function testLookupByPlateReturnsEmptyWhenNotFound(): void
    {
        $garage = $this->createGarage('lookup_plate_none');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $this->client->request('GET', '/api/garage/lookup?plate=UNKNOWN99', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNull($data['vehicle']);
        $this->assertNull($data['customer']);
    }

    public function testLookupEnforcesStrictTenantIsolation(): void
    {
        $garageA = $this->createGarage('lookup_tenant_a');
        $garageB = $this->createGarage('lookup_tenant_b');

        $userA = $this->createGarageUser($garageA);
        $tokenA = $this->getJwtToken($userA);

        $customerB = $this->createCustomer($garageB, [
            'firstName' => 'Secret',
            'lastName' => 'Customer',
            'phone' => '+306999999999',
        ]);

        $vehicleB = $this->createVehicle($garageB, $customerB, [
            'licensePlate' => 'SEC-9999',
            'make' => 'Ducati',
            'model' => 'Panigale',
        ]);

        // User A searches for User B's customer by phone
        $this->client->request('GET', '/api/garage/lookup?phone=6999999999', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($data['customer'], 'Mechanic A must not see Customer B');
        $this->assertSame([], $data['vehicles']);

        // User A searches for User B's vehicle by plate
        $this->client->request('GET', '/api/garage/lookup?plate=sec9999', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenA,
        ]);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($data['vehicle'], 'Mechanic A must not see Vehicle B');
    }

    private function createWorkOrder(Garage $garage, Customer $customer, Vehicle $vehicle, array $overrides = []): WorkOrder
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $managedGarage = $em->find(Garage::class, $garage->getId());
        $managedCustomer = $em->find(Customer::class, $customer->getId());
        $managedVehicle = $em->find(Vehicle::class, $vehicle->getId());

        $wo = new WorkOrder();
        $wo->setGarage($managedGarage);
        $wo->setCustomer($managedCustomer);
        $wo->setVehicle($managedVehicle);
        $wo->setDescription($overrides['description'] ?? 'Lookup test repair');
        $wo->setDate($overrides['date'] ?? new \DateTimeImmutable('today'));
        $wo->setStatus($overrides['status'] ?? 'checked_in');
        $wo->setPrice($overrides['price'] ?? '50.00');

        if (isset($overrides['pickedUpAt'])) {
            $wo->setPickedUpAt($overrides['pickedUpAt']);
        }

        $em->persist($wo);
        $em->flush();

        return $wo;
    }

    public function testLookupByPhoneIndicatesActiveWorkOrder(): void
    {
        $garage = $this->createGarage('lookup_phone_active_wo');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $customer = $this->createCustomer($garage, [
            'firstName' => 'Αλέξανδρος',
            'lastName' => 'Ιωάννου',
            'phone' => '+306911112222',
        ]);

        $vehicle = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'ACT-1001',
            'make' => 'Yamaha',
            'model' => 'XMAX 300',
        ]);

        // 1. Initially no active work order
        $this->client->request('GET', '/api/garage/lookup?phone=6911112222', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['vehicles'][0]['hasActiveWorkOrder']);
        $this->assertNull($data['vehicles'][0]['activeWorkOrder']);

        // 2. Add an active work order
        $wo = $this->createWorkOrder($garage, $customer, $vehicle, [
            'status' => 'checked_in',
            'description' => 'Oil & filters',
        ]);

        $this->client->request('GET', '/api/garage/lookup?phone=6911112222', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['vehicles'][0]['hasActiveWorkOrder']);
        $this->assertNotNull($data['vehicles'][0]['activeWorkOrder']);
        $this->assertSame($wo->getId()->toRfc4122(), $data['vehicles'][0]['activeWorkOrder']['id']);
        $this->assertSame('checked_in', $data['vehicles'][0]['activeWorkOrder']['status']);
        $this->assertSame('Oil & filters', $data['vehicles'][0]['activeWorkOrder']['description']);

        // 3. Mark work order as delivered
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $managedWo = $em->find(WorkOrder::class, $wo->getId());
        $managedWo->setStatus('delivered');
        $managedWo->setPickedUpAt(new \DateTimeImmutable());
        $em->flush();

        $this->client->request('GET', '/api/garage/lookup?phone=6911112222', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['vehicles'][0]['hasActiveWorkOrder']);
        $this->assertNull($data['vehicles'][0]['activeWorkOrder']);
    }

    public function testLookupByPlateIndicatesActiveWorkOrder(): void
    {
        $garage = $this->createGarage('lookup_plate_active_wo');
        $user = $this->createGarageUser($garage);
        $token = $this->getJwtToken($user);

        $customer = $this->createCustomer($garage, [
            'phone' => '+306933334444',
        ]);

        $vehicle = $this->createVehicle($garage, $customer, [
            'licensePlate' => 'PLA-2002',
            'make' => 'Honda',
            'model' => 'CB500X',
        ]);

        $wo = $this->createWorkOrder($garage, $customer, $vehicle, [
            'status' => 'in_progress',
            'description' => 'Brake service',
        ]);

        $this->client->request('GET', '/api/garage/lookup?plate=pla2002', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotNull($data['vehicle']);
        $this->assertTrue($data['vehicle']['hasActiveWorkOrder']);
        $this->assertNotNull($data['vehicle']['activeWorkOrder']);
        $this->assertSame($wo->getId()->toRfc4122(), $data['vehicle']['activeWorkOrder']['id']);
        $this->assertSame('in_progress', $data['vehicle']['activeWorkOrder']['status']);
        $this->assertSame('Brake service', $data['vehicle']['activeWorkOrder']['description']);
    }
}
