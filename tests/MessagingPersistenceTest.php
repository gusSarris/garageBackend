<?php

namespace App\Tests;

use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\Notification;
use App\Entity\SmsTemplate;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class MessagingPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testNotificationPersistenceWithDefaultsAndUuid(): void
    {
        $garage = new Garage();
        $garage->setName('Messaging Auto Care');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@msg-care.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6971111111');
        $customer->setFirstName('Σπύρος');
        $customer->setLastName('Αλεξίου');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('NXZ-8899');
        $vehicle->setMake('Honda');
        $vehicle->setModel('SH 150');
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDate(new \DateTimeImmutable('2026-09-20'));
        $workOrder->setDescription('Γενικό Service & τακάκια');
        $workOrder->setPrice('120.00');
        $this->entityManager->persist($workOrder);

        $notification = new Notification();
        // Check constructor defaults
        $this->assertSame('sent', $notification->getStatus());
        $this->assertSame('native_deep_link', $notification->getProvider());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getCreatedAt());

        $notification->setGarage($garage);
        $notification->setCustomer($customer);
        $notification->setVehicle($vehicle);
        $notification->setWorkOrder($workOrder);
        $notification->setType('quote');
        $notification->setChannel('sms');
        $notification->setRecipientPhone('6971111111');
        $notification->setMessageBody('Το κόστος επισκευής ανέρχεται στα 120.00€. Παρακαλούμε εγκρίνετε.');
        $notification->setCost('0.0500');
        $notification->setSentAt(new \DateTimeImmutable('2026-09-20 10:30:00'));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->assertNotNull($notification->getId());
        $this->assertInstanceOf(Uuid::class, $notification->getId());
        $this->assertTrue(Uuid::isValid($notification->getId()->toRfc4122()));

        $notificationId = $notification->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(Notification::class, $notificationId);
        $this->assertNotNull($retrieved);
        $this->assertSame('quote', $retrieved->getType());
        $this->assertSame('sms', $retrieved->getChannel());
        $this->assertSame('6971111111', $retrieved->getRecipientPhone());
        $this->assertSame('Το κόστος επισκευής ανέρχεται στα 120.00€. Παρακαλούμε εγκρίνετε.', $retrieved->getMessageBody());
        $this->assertSame('sent', $retrieved->getStatus());
        $this->assertSame('native_deep_link', $retrieved->getProvider());
        $this->assertSame('0.0500', $retrieved->getCost());
        $this->assertSame('2026-09-20 10:30:00', $retrieved->getSentAt()->format('Y-m-d H:i:s'));
        $this->assertNotNull($retrieved->getGarage());
        $this->assertTrue($retrieved->getGarage()->getId()->equals($garage->getId()));
        $this->assertNotNull($retrieved->getCustomer());
        $this->assertTrue($retrieved->getCustomer()->getId()->equals($customer->getId()));
        $this->assertNotNull($retrieved->getVehicle());
        $this->assertTrue($retrieved->getVehicle()->getId()->equals($vehicle->getId()));
        $this->assertNotNull($retrieved->getWorkOrder());
        $this->assertTrue($retrieved->getWorkOrder()->getId()->equals($workOrder->getId()));
    }

    public function testSmsTemplatePersistenceWithDefaultsAndUuid(): void
    {
        $garage = new Garage();
        $garage->setName('Template Test Garage');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@template-test.example.com');
        $this->entityManager->persist($garage);

        $template = new SmsTemplate();
        // Check constructor defaults
        $this->assertSame('all', $template->getChannel());
        $this->assertTrue($template->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $template->getCreatedAt());

        $template->setGarage($garage);
        $template->setType('ready_for_pickup');
        $template->setChannel('sms');
        $template->setTitle('Ειδοποίηση Ετοιμότητας (SMS)');
        $template->setBody('Το όχημα {plate} είναι έτοιμο! Τελικό κόστος: {price}€. Συνεργείο {garage_name}.');

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        $this->assertNotNull($template->getId());
        $this->assertInstanceOf(Uuid::class, $template->getId());
        $this->assertTrue(Uuid::isValid($template->getId()->toRfc4122()));

        $templateId = $template->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(SmsTemplate::class, $templateId);
        $this->assertNotNull($retrieved);
        $this->assertSame('ready_for_pickup', $retrieved->getType());
        $this->assertSame('sms', $retrieved->getChannel());
        $this->assertSame('Ειδοποίηση Ετοιμότητας (SMS)', $retrieved->getTitle());
        $this->assertStringContainsString('{plate}', $retrieved->getBody());
        $this->assertTrue($retrieved->isActive());
        $this->assertNotNull($retrieved->getGarage());
        $this->assertTrue($retrieved->getGarage()->getId()->equals($garage->getId()));
    }

    public function testCascadeRemovalWhenGarageIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Cascade Garage');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@cascade-test.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6972222222');
        $this->entityManager->persist($customer);

        $notification = new Notification();
        $notification->setGarage($garage);
        $notification->setCustomer($customer);
        $notification->setType('custom');
        $notification->setChannel('sms');
        $notification->setRecipientPhone('6972222222');
        $notification->setMessageBody('Δοκιμαστικό μήνυμα');
        $garage->addNotification($notification);
        $this->entityManager->persist($notification);

        $template = new SmsTemplate();
        $template->setGarage($garage);
        $template->setType('quote');
        $template->setTitle('Πρότυπο Προσφοράς');
        $template->setBody('Κόστος: {price}€');
        $garage->addSmsTemplate($template);
        $this->entityManager->persist($template);

        $this->entityManager->flush();

        $garageId = $garage->getId();
        $notificationId = $notification->getId();
        $templateId = $template->getId();

        $this->entityManager->clear();

        $retrievedGarage = $this->entityManager->find(Garage::class, $garageId);
        $this->assertNotNull($retrievedGarage);
        $this->assertCount(1, $retrievedGarage->getNotifications());
        $this->assertCount(1, $retrievedGarage->getSmsTemplates());

        $this->entityManager->remove($retrievedGarage);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Garage, Notification, and SmsTemplate must all be deleted
        $this->assertNull($this->entityManager->find(Garage::class, $garageId));
        $this->assertNull($this->entityManager->find(Notification::class, $notificationId));
        $this->assertNull($this->entityManager->find(SmsTemplate::class, $templateId));
    }

    public function testCascadeRemovalWhenCustomerIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Customer Cascade Garage');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@cust-cascade.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6973333333');
        $this->entityManager->persist($customer);

        $notification = new Notification();
        $notification->setGarage($garage);
        $notification->setCustomer($customer);
        $notification->setType('ready_for_pickup');
        $notification->setChannel('viber');
        $notification->setRecipientPhone('6973333333');
        $notification->setMessageBody('Έτοιμο για παραλαβή');
        $customer->addNotification($notification);
        $this->entityManager->persist($notification);

        $this->entityManager->flush();

        $customerId = $customer->getId();
        $notificationId = $notification->getId();

        $this->entityManager->clear();

        $retrievedCustomer = $this->entityManager->find(Customer::class, $customerId);
        $this->assertNotNull($retrievedCustomer);
        $this->assertCount(1, $retrievedCustomer->getNotifications());

        $this->entityManager->remove($retrievedCustomer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(Customer::class, $customerId));
        $this->assertNull($this->entityManager->find(Notification::class, $notificationId));
    }

    public function testSetNullWhenVehicleOrWorkOrderIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('SetNull Test Garage');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@setnull.example.com');
        $this->entityManager->persist($garage);

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone('6974444444');
        $this->entityManager->persist($customer);

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate('XEA-1122');
        $vehicle->setMake('Yamaha');
        $vehicle->setModel('TMAX');
        $this->entityManager->persist($vehicle);

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setCustomer($customer);
        $workOrder->setVehicle($vehicle);
        $workOrder->setDate(new \DateTimeImmutable('2026-09-20'));
        $workOrder->setDescription('Αλλαγή ιμάντα');
        $this->entityManager->persist($workOrder);

        $notification = new Notification();
        $notification->setGarage($garage);
        $notification->setCustomer($customer);
        $notification->setVehicle($vehicle);
        $notification->setWorkOrder($workOrder);
        $notification->setType('service_reminder');
        $notification->setChannel('sms');
        $notification->setRecipientPhone('6974444444');
        $notification->setMessageBody('Υπενθύμιση service για το TMAX');
        $this->entityManager->persist($notification);

        $this->entityManager->flush();

        $vehicleId = $vehicle->getId();
        $workOrderId = $workOrder->getId();
        $notificationId = $notification->getId();

        $this->entityManager->clear();

        // 1. Delete workOrder first: Notification must remain, workOrder reference becomes null, vehicle remains
        $retrievedWorkOrder = $this->entityManager->find(WorkOrder::class, $workOrderId);
        $this->entityManager->remove($retrievedWorkOrder);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $retrievedNotification = $this->entityManager->find(Notification::class, $notificationId);
        $this->assertNotNull($retrievedNotification);
        $this->assertNull($retrievedNotification->getWorkOrder());
        $this->assertNotNull($retrievedNotification->getVehicle());

        // 2. Delete vehicle next: Notification must remain, vehicle reference becomes null
        $retrievedVehicle = $this->entityManager->find(Vehicle::class, $vehicleId);
        $this->entityManager->remove($retrievedVehicle);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $retrievedNotification2 = $this->entityManager->find(Notification::class, $notificationId);
        $this->assertNotNull($retrievedNotification2);
        $this->assertNull($retrievedNotification2->getVehicle());
        $this->assertNull($retrievedNotification2->getWorkOrder());
        $this->assertNotNull($retrievedNotification2->getCustomer());
    }

    public function testParentCollectionsAddAndRemove(): void
    {
        $garage = new Garage();
        $customer = new Customer();
        $vehicle = new Vehicle();
        $workOrder = new WorkOrder();
        $notification = new Notification();
        $template = new SmsTemplate();

        // Garage collections
        $garage->addNotification($notification);
        $this->assertTrue($garage->getNotifications()->contains($notification));
        $this->assertSame($garage, $notification->getGarage());

        $garage->removeNotification($notification);
        $this->assertFalse($garage->getNotifications()->contains($notification));
        $this->assertNull($notification->getGarage());

        $garage->addSmsTemplate($template);
        $this->assertTrue($garage->getSmsTemplates()->contains($template));
        $this->assertSame($garage, $template->getGarage());

        $garage->removeSmsTemplate($template);
        $this->assertFalse($garage->getSmsTemplates()->contains($template));
        $this->assertNull($template->getGarage());

        // Customer collection
        $customer->addNotification($notification);
        $this->assertTrue($customer->getNotifications()->contains($notification));
        $this->assertSame($customer, $notification->getCustomer());

        $customer->removeNotification($notification);
        $this->assertFalse($customer->getNotifications()->contains($notification));
        $this->assertNull($notification->getCustomer());

        // Vehicle collection
        $vehicle->addNotification($notification);
        $this->assertTrue($vehicle->getNotifications()->contains($notification));
        $this->assertSame($vehicle, $notification->getVehicle());

        $vehicle->removeNotification($notification);
        $this->assertFalse($vehicle->getNotifications()->contains($notification));
        $this->assertNull($notification->getVehicle());

        // WorkOrder collection
        $workOrder->addNotification($notification);
        $this->assertTrue($workOrder->getNotifications()->contains($notification));
        $this->assertSame($workOrder, $notification->getWorkOrder());

        $workOrder->removeNotification($notification);
        $this->assertFalse($workOrder->getNotifications()->contains($notification));
        $this->assertNull($notification->getWorkOrder());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

