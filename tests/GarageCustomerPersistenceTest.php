<?php

namespace App\Tests;

use App\Entity\Customer;
use App\Entity\Garage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class GarageCustomerPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testGaragePersistenceWithDefaultsAndUuid(): void
    {
        $garage = new Garage();
        $garage->setName('Fast Fix Auto');
        $garage->setEmail('contact@fastfix.example.com');
        $garage->setPhone('2101234567');
        $garage->setVatNumber('099999999');
        $garage->setTaxOffice('DOY Cholargou');
        $garage->setAddress('Mesogeion 123');
        $garage->setCity('Athens');
        $garage->setPostalCode('11527');

        // Check defaults from constructor
        $this->assertSame('trial', $garage->getSubscriptionStatus());
        $this->assertTrue($garage->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $garage->getCreatedAt());

        $this->entityManager->persist($garage);
        $this->entityManager->flush();

        $this->assertNotNull($garage->getId());
        $this->assertInstanceOf(Uuid::class, $garage->getId());
        $this->assertTrue(Uuid::isValid($garage->getId()->toRfc4122()));

        // Verify retrieval from database
        $this->entityManager->clear();
        $retrievedGarage = $this->entityManager->find(Garage::class, $garage->getId());

        $this->assertNotNull($retrievedGarage);
        $this->assertSame('Fast Fix Auto', $retrievedGarage->getName());
        $this->assertSame('contact@fastfix.example.com', $retrievedGarage->getEmail());
        $this->assertSame('099999999', $retrievedGarage->getVatNumber());
        $this->assertSame('Athens', $retrievedGarage->getCity());
    }

    public function testCustomerPersistenceWithGarageAndB2BFields(): void
    {
        $garage = new Garage();
        $garage->setName('City Motors');
        $garage->setEmail('city@motors.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setFirstName('Nikos');
        $customer->setLastName('Papadopoulos');
        $customer->setPhone('6980000001');
        $customer->setSecondaryPhone('2109876543');
        $customer->setEmail('nikos@example.com');
        $customer->setCompanyName('Fast Courier Express');
        $customer->setVatNumber('123456789');
        $customer->setTaxOffice('DOY Athinon');
        $customer->setAddress('Stadiou 5');
        $customer->setCity('Athens');
        $customer->setPostalCode('10562');
        $customer->setNotes('Fleet delivery van servicing');

        $this->assertInstanceOf(\DateTimeImmutable::class, $customer->getCreatedAt());

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->assertNotNull($customer->getId());
        $this->assertInstanceOf(Uuid::class, $customer->getId());
        $this->assertTrue(Uuid::isValid($customer->getId()->toRfc4122()));

        // Verify retrieval with relation and B2B fields
        $this->entityManager->clear();
        $retrievedCustomer = $this->entityManager->find(Customer::class, $customer->getId());

        $this->assertNotNull($retrievedCustomer);
        $this->assertSame('Fast Courier Express', $retrievedCustomer->getCompanyName());
        $this->assertSame('123456789', $retrievedCustomer->getVatNumber());
        $this->assertSame('6980000001', $retrievedCustomer->getPhone());
        $this->assertNotNull($retrievedCustomer->getGarage());
        $this->assertTrue($retrievedCustomer->getGarage()->getId()->equals($garage->getId()));
    }

    public function testCascadeRemovalOfCustomersWhenGarageIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Autocare Workshop');
        $garage->setEmail('info@autocare.example.com');
        $this->entityManager->persist($garage);

        $customer1 = new Customer();
        $customer1->setGarage($garage);
        $customer1->setPhone('6980000002');

        $customer2 = new Customer();
        $customer2->setGarage($garage);
        $customer2->setPhone('6980000003');

        $garage->addCustomer($customer1);
        $garage->addCustomer($customer2);

        $this->entityManager->persist($customer1);
        $this->entityManager->persist($customer2);
        $this->entityManager->flush();

        $garageId = $garage->getId();
        $customer1Id = $customer1->getId();
        $customer2Id = $customer2->getId();

        $this->entityManager->clear();

        // Retrieve and delete the garage
        $retrievedGarage = $this->entityManager->find(Garage::class, $garageId);
        $this->assertNotNull($retrievedGarage);
        $this->assertCount(2, $retrievedGarage->getCustomers());

        $this->entityManager->remove($retrievedGarage);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Both garage and its customers must be gone
        $this->assertNull($this->entityManager->find(Garage::class, $garageId));
        $this->assertNull($this->entityManager->find(Customer::class, $customer1Id));
        $this->assertNull($this->entityManager->find(Customer::class, $customer2Id));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

