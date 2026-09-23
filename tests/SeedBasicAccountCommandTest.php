<?php

namespace App\Tests;

use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use App\Repository\CustomerRepository;
use App\Repository\GarageRepository;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use App\Repository\WorkOrderRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SeedBasicAccountCommandTest extends KernelTestCase
{
    public function testSeedBasicAccountCommandExecutionAndIdempotency(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:seed-basic-account');
        $commandTester = new CommandTester($command);

        // First execution
        $commandTester->execute([]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('successfully seeded', $output);
        $this->assertStringContainsString('sarriskonst@gmail.com', $output);
        $this->assertStringContainsString('sarr-c@hotmail.com', $output);
        $this->assertStringContainsString('Sarris Auto Service', $output);

        $container = static::getContainer();
        $userRepo = $container->get(UserRepository::class);
        $garageRepo = $container->get(GarageRepository::class);
        $customerRepo = $container->get(CustomerRepository::class);
        $vehicleRepo = $container->get(VehicleRepository::class);
        $workOrderRepo = $container->get(WorkOrderRepository::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Verify Super Admin
        $superAdmin = $userRepo->findOneBy(['email' => 'sarriskonst@gmail.com']);
        $this->assertNotNull($superAdmin);
        $this->assertContains('ROLE_SUPER_ADMIN', $superAdmin->getRoles());
        $this->assertTrue($superAdmin->isActive());
        $this->assertNull($superAdmin->getGarage());
        $this->assertTrue($passwordHasher->isPasswordValid($superAdmin, '12345'));

        // Verify Garage
        $garage = $garageRepo->findOneBy(['email' => 'contact@sarris-autoservice.gr']);
        $this->assertNotNull($garage);
        $this->assertSame('Sarris Auto Service', $garage->getName());
        $this->assertTrue($garage->isActive());
        $this->assertSame('active', $garage->getSubscriptionStatus());

        // Verify Garage Owner
        $owner = $userRepo->findOneBy(['email' => 'sarr-c@hotmail.com']);
        $this->assertNotNull($owner);
        $this->assertContains('ROLE_GARAGE_ADMIN', $owner->getRoles());
        $this->assertTrue($owner->isActive());
        $this->assertSame($garage->getId()->toRfc4122(), $owner->getGarage()->getId()->toRfc4122());
        $this->assertTrue($passwordHasher->isPasswordValid($owner, '12345'));

        // Verify 10 Customers
        $customers = $customerRepo->findBy(['garage' => $garage]);
        $this->assertCount(10, $customers);

        // Verify 11 Vehicles (10 customers, first customer Nikos has 2 vehicles)
        $vehicles = $vehicleRepo->findBy(['garage' => $garage]);
        $this->assertCount(11, $vehicles);
        $customerIdsWithVehicles = [];
        foreach ($vehicles as $vehicle) {
            $customerIdsWithVehicles[$vehicle->getCustomer()->getId()->toRfc4122()] = true;
        }
        $this->assertCount(10, $customerIdsWithVehicles);

        // Verify 9 Work Orders
        $workOrders = $workOrderRepo->findBy(['garage' => $garage]);
        $this->assertCount(9, $workOrders);

        $statuses = array_map(fn (WorkOrder $wo) => $wo->getStatus(), $workOrders);
        $this->assertContains('checked_in', $statuses);
        $this->assertContains('in_progress', $statuses);
        $this->assertContains('completed', $statuses);
        $this->assertContains('delivered', $statuses);
        $this->assertContains('cancelled', $statuses);

        // Second execution (Idempotency test)
        $commandTester->execute(['--reset' => true]);
        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());

        $customersSecondRun = $customerRepo->findBy(['garage' => $garage]);
        $this->assertCount(10, $customersSecondRun);

        $vehiclesSecondRun = $vehicleRepo->findBy(['garage' => $garage]);
        $this->assertCount(11, $vehiclesSecondRun);

        $workOrdersSecondRun = $workOrderRepo->findBy(['garage' => $garage]);
        $this->assertCount(9, $workOrdersSecondRun);
    }
}

