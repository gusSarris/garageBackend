<?php

namespace App\Tests;

use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class WorkOrderPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testWorkOrderPersistenceWithDefaultsAndUuid(): void
    {
        $garage = new Garage();
        $garage->setName('Alpha Auto Workshop');
        $garage->setEmail('contact@alpha-workshop.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6910000001');
        $customer->setFirstName('Nikos');
        $customer->setLastName('Oikonomou');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('IBZ-1234');
        $vehicle->setMake('Toyota');
        $vehicle->setModel('Auris');
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        // Check constructor defaults
        $this->assertSame('checked_in', $workOrder->getStatus());
        $this->assertSame('0.00', $workOrder->getPrice());
        $this->assertInstanceOf(\DateTimeImmutable::class, $workOrder->getCreatedAt());

        $date = new \DateTimeImmutable('2026-09-20');
        $workOrder->setDate($date);
        $workOrder->setDescription('Τακτικό service 60.000km, αλλαγή λαδιών και φίλτρων');
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);

        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();

        $this->assertNotNull($workOrder->getId());
        $this->assertInstanceOf(Uuid::class, $workOrder->getId());
        $this->assertTrue(Uuid::isValid($workOrder->getId()->toRfc4122()));

        $workOrderId = $workOrder->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(WorkOrder::class, $workOrderId);
        $this->assertNotNull($retrieved);
        $this->assertSame('2026-09-20', $retrieved->getDate()->format('Y-m-d'));
        $this->assertSame('checked_in', $retrieved->getStatus());
        $this->assertSame('0.00', $retrieved->getPrice());
        $this->assertSame('Τακτικό service 60.000km, αλλαγή λαδιών και φίλτρων', $retrieved->getDescription());
        $this->assertNull($retrieved->getOdometerKm());
        $this->assertNull($retrieved->getNotes());
        $this->assertNull($retrieved->getPartsNotes());
        $this->assertNull($retrieved->getScheduledTime());
        $this->assertNotNull($retrieved->getGarage());
        $this->assertTrue($retrieved->getGarage()->getId()->equals($garage->getId()));
        $this->assertNotNull($retrieved->getCustomer());
        $this->assertTrue($retrieved->getCustomer()->getId()->equals($customer->getId()));
        $this->assertNotNull($retrieved->getVehicle());
        $this->assertTrue($retrieved->getVehicle()->getId()->equals($vehicle->getId()));
    }

    public function testWorkOrderLifecycleAndAttributesPersistence(): void
    {
        $garage = new Garage();
        $garage->setName('Beta Service Center');
        $garage->setEmail('contact@beta-service.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6910000002');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('YNA-8877');
        $vehicle->setMake('Ford');
        $vehicle->setModel('Focus');
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDate(new \DateTimeImmutable('2026-09-21'));
        $workOrder->setStatus('waiting_parts');
        $workOrder->setScheduledTime('11:30');
        $workOrder->setDescription('Αλλαγή σετ συμπλέκτη και βαλβολίνες');
        $workOrder->setPrice('320.50');
        $workOrder->setOdometerKm(145200);
        $workOrder->setNotes('Έλεγχος και για διαρροή τσιμούχας στροφάλου.');
        $workOrder->setPartsNotes('Παραγγελία σετ Sachs ref: 3000 951 024');
        $workOrder->setCheckedInAt(new \DateTimeImmutable('2026-09-21 09:00:00'));
        $workOrder->setCompletedAt(new \DateTimeImmutable('2026-09-21 16:30:00'));
        $workOrder->setPickedUpAt(new \DateTimeImmutable('2026-09-21 18:00:00'));
        $workOrder->setUpdatedAt(new \DateTimeImmutable('2026-09-21 18:05:00'));

        $this->entityManager->persist($workOrder);
        $this->entityManager->flush();

        $workOrderId = $workOrder->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(WorkOrder::class, $workOrderId);
        $this->assertNotNull($retrieved);
        $this->assertSame('2026-09-21', $retrieved->getDate()->format('Y-m-d'));
        $this->assertSame('waiting_parts', $retrieved->getStatus());
        $this->assertSame('11:30', $retrieved->getScheduledTime());
        $this->assertSame('Αλλαγή σετ συμπλέκτη και βαλβολίνες', $retrieved->getDescription());
        $this->assertSame('320.50', $retrieved->getPrice());
        $this->assertSame(145200, $retrieved->getOdometerKm());
        $this->assertSame('Έλεγχος και για διαρροή τσιμούχας στροφάλου.', $retrieved->getNotes());
        $this->assertSame('Παραγγελία σετ Sachs ref: 3000 951 024', $retrieved->getPartsNotes());
        $this->assertSame('2026-09-21 09:00:00', $retrieved->getCheckedInAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 16:30:00', $retrieved->getCompletedAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 18:00:00', $retrieved->getPickedUpAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 18:05:00', $retrieved->getUpdatedAt()->format('Y-m-d H:i:s'));
    }

    public function testCascadeRemovalWhenGarageIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Gamma Motors');
        $garage->setEmail('gamma@motors.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6910000003');
        $garage->addCustomer($customer);
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('XEA-4433');
        $vehicle->setMake('Hyundai');
        $vehicle->setModel('i20');
        $garage->addVehicle($vehicle);
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDate(new \DateTimeImmutable('2026-09-20'));
        $workOrder->setDescription('Έλεγχος ΚΤΕΟ & κάρτα καυσαερίων');
        $garage->addWorkOrder($workOrder);
        $this->entityManager->persist($workOrder);

        $this->entityManager->flush();

        $garageId = $garage->getId();
        $customerId = $customer->getId();
        $vehicleId = $vehicle->getId();
        $workOrderId = $workOrder->getId();

        $this->entityManager->clear();

        $retrievedGarage = $this->entityManager->find(Garage::class, $garageId);
        $this->assertNotNull($retrievedGarage);
        $this->assertCount(1, $retrievedGarage->getWorkOrders());

        $this->entityManager->remove($retrievedGarage);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Garage::class, $garageId));
        $this->assertNull($this->entityManager->find(Customer::class, $customerId));
        $this->assertNull($this->entityManager->find(Vehicle::class, $vehicleId));
        $this->assertNull($this->entityManager->find(WorkOrder::class, $workOrderId));
    }

    public function testCascadeRemovalWhenCustomerIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Delta Express');
        $garage->setEmail('delta@express.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6910000004');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('PEE-9988');
        $vehicle->setMake('Nissan');
        $vehicle->setModel('Micra');
        $customer->addVehicle($vehicle);
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDate(new \DateTimeImmutable('2026-09-20'));
        $workOrder->setDescription('Επισκευή κλιματιστικού A/C');
        $customer->addWorkOrder($workOrder);
        $this->entityManager->persist($workOrder);

        $this->entityManager->flush();

        $customerId = $customer->getId();
        $vehicleId = $vehicle->getId();
        $workOrderId = $workOrder->getId();

        $this->entityManager->clear();

        $retrievedCustomer = $this->entityManager->find(Customer::class, $customerId);
        $this->assertNotNull($retrievedCustomer);
        $this->assertCount(1, $retrievedCustomer->getWorkOrders());

        $this->entityManager->remove($retrievedCustomer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Customer::class, $customerId));
        $this->assertNull($this->entityManager->find(Vehicle::class, $vehicleId));
        $this->assertNull($this->entityManager->find(WorkOrder::class, $workOrderId));
    }

    public function testCascadeRemovalWhenVehicleIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Epsilon Fleet');
        $garage->setEmail('epsilon@fleet.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6910000005');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('ZHB-7766');
        $vehicle->setMake('BMW');
        $vehicle->setModel('116i');
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDate(new \DateTimeImmutable('2026-09-20'));
        $workOrder->setDescription('Αλλαγή δισκόπλακες εμπρός');
        $vehicle->addWorkOrder($workOrder);
        $this->entityManager->persist($workOrder);

        $this->entityManager->flush();

        $garageId = $garage->getId();
        $customerId = $customer->getId();
        $vehicleId = $vehicle->getId();
        $workOrderId = $workOrder->getId();

        $this->entityManager->clear();

        $retrievedVehicle = $this->entityManager->find(Vehicle::class, $vehicleId);
        $this->assertNotNull($retrievedVehicle);
        $this->assertCount(1, $retrievedVehicle->getWorkOrders());

        $this->entityManager->remove($retrievedVehicle);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Vehicle::class, $vehicleId));
        $this->assertNull($this->entityManager->find(WorkOrder::class, $workOrderId));

        // Garage and customer must still exist
        $this->assertNotNull($this->entityManager->find(Garage::class, $garageId));
        $this->assertNotNull($this->entityManager->find(Customer::class, $customerId));
    }

    public function testParentCollectionsAddAndRemove(): void
    {
        $garage = new Garage();
        $customer = new Customer();
        $vehicle = new Vehicle();
        $workOrder = new WorkOrder();

        // Garage collection
        $garage->addWorkOrder($workOrder);
        $this->assertTrue($garage->getWorkOrders()->contains($workOrder));
        $this->assertSame($garage, $workOrder->getGarage());

        $garage->removeWorkOrder($workOrder);
        $this->assertFalse($garage->getWorkOrders()->contains($workOrder));
        $this->assertNull($workOrder->getGarage());

        // Customer collection
        $customer->addWorkOrder($workOrder);
        $this->assertTrue($customer->getWorkOrders()->contains($workOrder));
        $this->assertSame($customer, $workOrder->getCustomer());

        $customer->removeWorkOrder($workOrder);
        $this->assertFalse($customer->getWorkOrders()->contains($workOrder));
        $this->assertNull($workOrder->getCustomer());

        // Vehicle collection
        $vehicle->addWorkOrder($workOrder);
        $this->assertTrue($vehicle->getWorkOrders()->contains($workOrder));
        $this->assertSame($vehicle, $workOrder->getVehicle());

        $vehicle->removeWorkOrder($workOrder);
        $this->assertFalse($vehicle->getWorkOrders()->contains($workOrder));
        $this->assertNull($workOrder->getVehicle());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

