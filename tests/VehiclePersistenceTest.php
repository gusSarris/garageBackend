<?php

namespace App\Tests;

use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class VehiclePersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testVehiclePersistenceWithDefaultsAndUuid(): void
    {
        $garage = new Garage();
        $garage->setName('Athens Auto Repair');
        $garage->setEmail('contact@athensautorepair.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6911111111');
        $customer->setFirstName('Kostas');
        $customer->setLastName('Papadopoulos');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $this->assertTrue($vehicle->isAllowReminders());
        $this->assertInstanceOf(\DateTimeImmutable::class, $vehicle->getCreatedAt());

        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('IKZ-4589');
        $vehicle->setMake('Toyota');
        $vehicle->setModel('Yaris');

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        $this->assertNotNull($vehicle->getId());
        $this->assertInstanceOf(Uuid::class, $vehicle->getId());
        $this->assertTrue(Uuid::isValid($vehicle->getId()->toRfc4122()));

        $vehicleId = $vehicle->getId();
        $this->entityManager->clear();

        $retrievedVehicle = $this->entityManager->find(Vehicle::class, $vehicleId);
        $this->assertNotNull($retrievedVehicle);
        $this->assertSame('IKZ-4589', $retrievedVehicle->getLicensePlate());
        $this->assertSame('Toyota', $retrievedVehicle->getMake());
        $this->assertSame('Yaris', $retrievedVehicle->getModel());
        $this->assertTrue($retrievedVehicle->isAllowReminders());
        $this->assertNotNull($retrievedVehicle->getGarage());
        $this->assertTrue($retrievedVehicle->getGarage()->getId()->equals($garage->getId()));
        $this->assertNotNull($retrievedVehicle->getCustomer());
        $this->assertTrue($retrievedVehicle->getCustomer()->getId()->equals($customer->getId()));
    }

    public function testVehicleMechanicalAndReminderFields(): void
    {
        $garage = new Garage();
        $garage->setName('Precision Mechanics');
        $garage->setEmail('info@precision.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6922222222');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('NHE-9102');
        $vehicle->setVin('WVWZZZ1KZAM123456');
        $vehicle->setMake('Volkswagen');
        $vehicle->setModel('Golf');
        $vehicle->setYear(2019);
        $vehicle->setEngineCode('CXXB');
        $vehicle->setEngineDisplacement(1598);
        $vehicle->setEnginePowerHp(115);
        $vehicle->setFuelType('diesel');
        $vehicle->setTransmission('manual');
        $vehicle->setColor('Grey Metallic');
        $vehicle->setFirstRegistrationDate(new \DateTimeImmutable('2019-04-15'));
        $vehicle->setMileage(112500);

        // Service & Reminder fields
        $vehicle->setLastServiceDate(new \DateTimeImmutable('2025-05-10'));
        $vehicle->setLastServiceMileage(105000);
        $vehicle->setNextKteoDate(new \DateTimeImmutable('2026-11-20'));
        $vehicle->setNextServiceDate(new \DateTimeImmutable('2026-05-10'));
        $vehicle->setNextServiceMileage(120000);
        $vehicle->setLastKteoReminderSentAt(new \DateTimeImmutable('2026-09-01 10:00:00'));
        $vehicle->setLastServiceReminderSentAt(new \DateTimeImmutable('2026-09-02 11:30:00'));
        $vehicle->setAllowReminders(false);
        $vehicle->setNotes('Timing belt replaced at 105k km. Requires 5W-30 Longlife oil.');

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        $vehicleId = $vehicle->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(Vehicle::class, $vehicleId);
        $this->assertNotNull($retrieved);
        $this->assertSame('WVWZZZ1KZAM123456', $retrieved->getVin());
        $this->assertSame(2019, $retrieved->getYear());
        $this->assertSame('CXXB', $retrieved->getEngineCode());
        $this->assertSame(1598, $retrieved->getEngineDisplacement());
        $this->assertSame(115, $retrieved->getEnginePowerHp());
        $this->assertSame('diesel', $retrieved->getFuelType());
        $this->assertSame('manual', $retrieved->getTransmission());
        $this->assertSame('Grey Metallic', $retrieved->getColor());
        $this->assertSame('2019-04-15', $retrieved->getFirstRegistrationDate()->format('Y-m-d'));
        $this->assertSame(112500, $retrieved->getMileage());
        $this->assertSame('2025-05-10', $retrieved->getLastServiceDate()->format('Y-m-d'));
        $this->assertSame(105000, $retrieved->getLastServiceMileage());
        $this->assertSame('2026-11-20', $retrieved->getNextKteoDate()->format('Y-m-d'));
        $this->assertSame('2026-05-10', $retrieved->getNextServiceDate()->format('Y-m-d'));
        $this->assertSame(120000, $retrieved->getNextServiceMileage());
        $this->assertSame('2026-09-01 10:00:00', $retrieved->getLastKteoReminderSentAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-02 11:30:00', $retrieved->getLastServiceReminderSentAt()->format('Y-m-d H:i:s'));
        $this->assertFalse($retrieved->isAllowReminders());
        $this->assertStringContainsString('Timing belt', $retrieved->getNotes());
    }

    public function testCascadeRemovalWhenCustomerIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Fleet Service Hub');
        $garage->setEmail('fleet@hub.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6933333333');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('ZXZ-1234');
        $vehicle->setMake('Ford');
        $vehicle->setModel('Transit');

        $customer->addVehicle($vehicle);
        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        $customerId = $customer->getId();
        $vehicleId = $vehicle->getId();

        $this->entityManager->clear();

        $retrievedCustomer = $this->entityManager->find(Customer::class, $customerId);
        $this->assertNotNull($retrievedCustomer);
        $this->assertCount(1, $retrievedCustomer->getVehicles());

        $this->entityManager->remove($retrievedCustomer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Customer::class, $customerId));
        $this->assertNull($this->entityManager->find(Vehicle::class, $vehicleId));
    }

    public function testCascadeRemovalWhenGarageIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Downtown Motors');
        $garage->setEmail('downtown@motors.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6944444444');
        $garage->addCustomer($customer);
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('BNE-5678');
        $vehicle->setMake('Opel');
        $vehicle->setModel('Corsa');

        $garage->addVehicle($vehicle);
        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        $garageId = $garage->getId();
        $vehicleId = $vehicle->getId();

        $this->entityManager->clear();

        $retrievedGarage = $this->entityManager->find(Garage::class, $garageId);
        $this->assertNotNull($retrievedGarage);

        $this->entityManager->remove($retrievedGarage);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Garage::class, $garageId));
        $this->assertNull($this->entityManager->find(Vehicle::class, $vehicleId));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
