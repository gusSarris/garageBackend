<?php

namespace App\Command;

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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-basic-account',
    description: 'Seeds basic accounts (Super Admin, Garage Owner), Workshop Garage, 10 Clients with vehicles, and 9 repairs',
)]
class SeedBasicAccountCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly GarageRepository $garageRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly WorkOrderRepository $workOrderRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Reset and recreate seed entities if they exist')] bool $reset = false,
    ): int {
        $io->title('ClickDrive — Basic Account & Demo Data Seeder');

        // 1. Super Admin Account
        $superAdminEmail = 'sarriskonst@gmail.com';
        $superAdmin = $this->userRepository->findOneBy(['email' => $superAdminEmail]);

        if ($superAdmin instanceof User && $reset) {
            $io->text(sprintf('Updating existing Super Admin "%s"...', $superAdminEmail));
        } elseif (!$superAdmin instanceof User) {
            $superAdmin = new User();
            $superAdmin->setEmail($superAdminEmail);
            $this->entityManager->persist($superAdmin);
            $io->text(sprintf('Creating Super Admin "%s"...', $superAdminEmail));
        }

        $superAdmin->setFullName('Konstantinos Sarris');
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN']);
        $superAdmin->setIsActive(true);
        $superAdmin->setGarage(null);
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, '12345'));

        // 2. Workshop Garage
        $garageEmail = 'contact@sarris-autoservice.gr';
        $garage = $this->garageRepository->findOneBy(['email' => $garageEmail]);

        if (!$garage instanceof Garage) {
            $garage = new Garage();
            $garage->setEmail($garageEmail);
            $this->entityManager->persist($garage);
            $io->text('Creating Workshop Garage "Sarris Auto Service"...');
        } else {
            $io->text('Using existing Workshop Garage "Sarris Auto Service"...');
        }

        $garage->setName('Sarris Auto Service');
        $garage->setVatNumber('094123456');
        $garage->setTaxOffice('Α\' Αθηνών');
        $garage->setPhone('+30 210 9876543');
        $garage->setAddress('Leoforos Vouliagmenis 120');
        $garage->setCity('Athens');
        $garage->setPostalCode('11636');
        $garage->setSubscriptionStatus('active');
        $garage->setIsActive(true);

        // 3. Garage Owner Account
        $ownerEmail = 'sarr-c@hotmail.com';
        $owner = $this->userRepository->findOneBy(['email' => $ownerEmail]);

        if ($owner instanceof User && $reset) {
            $io->text(sprintf('Updating existing Garage Owner "%s"...', $ownerEmail));
        } elseif (!$owner instanceof User) {
            $owner = new User();
            $owner->setEmail($ownerEmail);
            $this->entityManager->persist($owner);
            $io->text(sprintf('Creating Garage Owner "%s"...', $ownerEmail));
        }

        $owner->setFullName('Kostas Sarris');
        $owner->setRoles(['ROLE_GARAGE_ADMIN']);
        $owner->setIsActive(true);
        $owner->setGarage($garage);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, '12345'));

        // Flush so garage has UUID generated if newly created
        $this->entityManager->flush();

        // 4. 10 Clients (Customers)
        $customersData = [
            [
                'firstName' => 'Nikos',
                'lastName' => 'Papadopoulos',
                'phone' => '+306911111101',
                'email' => 'nikos.papadopoulos@example.com',
                'address' => 'Ermou 15',
                'city' => 'Athens',
                'postalCode' => '10563',
                'vatNumber' => '094123451',
            ],
            [
                'firstName' => 'Maria',
                'lastName' => 'Georgiou',
                'phone' => '+306911111102',
                'email' => 'maria.georgiou@example.com',
                'address' => 'Metaxa 22',
                'city' => 'Glyfada',
                'postalCode' => '16674',
                'vatNumber' => '094123452',
            ],
            [
                'firstName' => 'Dimitris',
                'lastName' => 'Oikonomou',
                'phone' => '+306911111103',
                'email' => 'dimitris.oikonomou@example.com',
                'address' => 'Thiseos 88',
                'city' => 'Kallithea',
                'postalCode' => '17671',
                'vatNumber' => '094123453',
            ],
            [
                'firstName' => 'Eleni',
                'lastName' => 'Dimitriou',
                'phone' => '+306911111104',
                'email' => 'eleni.dimitriou@example.com',
                'address' => 'Grigoriou Lampraki 45',
                'city' => 'Piraeus',
                'postalCode' => '18534',
                'vatNumber' => '094123454',
            ],
            [
                'firstName' => 'Giannis',
                'lastName' => 'Vasileiou',
                'phone' => '+306911111105',
                'email' => 'giannis.vasileiou@example.com',
                'address' => 'Kifisias 102',
                'city' => 'Marousi',
                'postalCode' => '15125',
                'vatNumber' => '094123455',
            ],
            [
                'firstName' => 'Sofia',
                'lastName' => 'Konstantinidou',
                'phone' => '+306911111106',
                'email' => 'sofia.k@example.com',
                'address' => 'El. Venizelou 33',
                'city' => 'Nea Smyrni',
                'postalCode' => '17121',
                'vatNumber' => '094123456',
            ],
            [
                'firstName' => 'Kostas',
                'lastName' => 'Nikolaou',
                'phone' => '+306911111107',
                'email' => 'kostas.nikolaou@example.com',
                'address' => 'Agias Paraskevis 12',
                'city' => 'Chalandri',
                'postalCode' => '15234',
                'vatNumber' => '094123457',
            ],
            [
                'firstName' => 'Anna',
                'lastName' => 'Karagianni',
                'phone' => '+306911111108',
                'email' => 'anna.karagianni@example.com',
                'address' => 'Panagi Tsaldari 55',
                'city' => 'Peristeri',
                'postalCode' => '12134',
                'vatNumber' => '094123458',
            ],
            [
                'firstName' => 'Panagiotis',
                'lastName' => 'Alexopoulos',
                'phone' => '+306911111109',
                'email' => 'p.alexopoulos@example.com',
                'address' => 'Sofokli Venizelou 19',
                'city' => 'Ilioupoli',
                'postalCode' => '16345',
                'vatNumber' => '094123459',
            ],
            [
                'firstName' => 'Katerina',
                'lastName' => 'Apostolou',
                'phone' => '+306911111110',
                'email' => 'katerina.apostolou@example.com',
                'address' => 'Kolokotroni 8',
                'city' => 'Kifisia',
                'postalCode' => '14562',
                'vatNumber' => '094123460',
            ],
        ];

        /** @var Customer[] $customerEntities */
        $customerEntities = [];
        foreach ($customersData as $cData) {
            $customer = $this->customerRepository->findOneBy([
                'garage' => $garage,
                'phone' => $cData['phone'],
            ]);

            if (!$customer instanceof Customer) {
                $customer = new Customer();
                $customer->setGarage($garage);
                $customer->setPhone($cData['phone']);
                $this->entityManager->persist($customer);
            }

            $customer->setFirstName($cData['firstName']);
            $customer->setLastName($cData['lastName']);
            $customer->setEmail($cData['email']);
            $customer->setAddress($cData['address']);
            $customer->setCity($cData['city']);
            $customer->setPostalCode($cData['postalCode']);
            $customer->setVatNumber($cData['vatNumber']);
            $customer->setTaxOffice('Α\' Αθηνών');

            $customerEntities[] = $customer;
        }

        // 5. 10 Vehicles (one per customer)
        $vehiclesData = [
            [
                'plate' => 'ΙΗΒ-1234',
                'vin' => 'VF3CCHMZ6KY000001',
                'make' => 'Peugeot',
                'model' => '208',
                'year' => 2019,
                'fuel' => 'petrol',
                'color' => 'White',
                'mileage' => 54000,
            ],
            [
                'plate' => 'ΖΤΜ-5678',
                'vin' => 'WBA11AK05M7000002',
                'make' => 'BMW',
                'model' => '118i',
                'year' => 2021,
                'fuel' => 'petrol',
                'color' => 'Black',
                'mileage' => 42000,
            ],
            [
                'plate' => 'ΝΗΚ-9012',
                'vin' => 'WVWZZZCDZLW000003',
                'make' => 'Volkswagen',
                'model' => 'Golf 8',
                'year' => 2020,
                'fuel' => 'petrol',
                'color' => 'Silver',
                'mileage' => 68000,
            ],
            [
                'plate' => 'ΥΧΙ-3456',
                'vin' => 'JMZDM6W7A00000004',
                'make' => 'Mazda',
                'model' => 'CX-30',
                'year' => 2022,
                'fuel' => 'hybrid',
                'color' => 'Red',
                'mileage' => 28000,
            ],
            [
                'plate' => 'ΙΒΟ-7890',
                'vin' => 'TMBJJ7NE7J0000005',
                'make' => 'Skoda',
                'model' => 'Octavia',
                'year' => 2018,
                'fuel' => 'diesel',
                'color' => 'Blue',
                'mileage' => 115000,
            ],
            [
                'plate' => 'ΚΗΜ-2345',
                'vin' => 'VF1RJA00564000006',
                'make' => 'Renault',
                'model' => 'Clio V',
                'year' => 2020,
                'fuel' => 'petrol',
                'color' => 'Orange',
                'mileage' => 49000,
            ],
            [
                'plate' => 'ΧΝΑ-6789',
                'vin' => 'JT1NB3FV50D000007',
                'make' => 'Toyota',
                'model' => 'Yaris Hybrid',
                'year' => 2023,
                'fuel' => 'hybrid',
                'color' => 'White',
                'mileage' => 18000,
            ],
            [
                'plate' => 'ΕΡΤ-0123',
                'vin' => 'WAUZZZ8V7HA000008',
                'make' => 'Audi',
                'model' => 'A3 Sportback',
                'year' => 2017,
                'fuel' => 'diesel',
                'color' => 'Grey',
                'mileage' => 132000,
            ],
            [
                'plate' => 'ΒΚΡ-4567',
                'vin' => 'ZFA3120000J000009',
                'make' => 'Fiat',
                'model' => '500 Hybrid',
                'year' => 2021,
                'fuel' => 'hybrid',
                'color' => 'Green',
                'mileage' => 31000,
            ],
            [
                'plate' => 'ABC-8901',
                'vin' => 'WDD1770841J000010',
                'make' => 'Mercedes-Benz',
                'model' => 'A 180',
                'year' => 2022,
                'fuel' => 'petrol',
                'color' => 'Black',
                'mileage' => 37000,
            ],
        ];

        /** @var Vehicle[] $vehicleEntities */
        $vehicleEntities = [];
        foreach ($vehiclesData as $index => $vData) {
            $customer = $customerEntities[$index];
            $vehicle = $this->vehicleRepository->findOneBy([
                'garage' => $garage,
                'licensePlate' => $vData['plate'],
            ]);

            if (!$vehicle instanceof Vehicle) {
                $vehicle = new Vehicle();
                $vehicle->setGarage($garage);
                $vehicle->setLicensePlate($vData['plate']);
                $this->entityManager->persist($vehicle);
            }

            $vehicle->setCustomer($customer);
            $vehicle->setVin($vData['vin']);
            $vehicle->setMake($vData['make']);
            $vehicle->setModel($vData['model']);
            $vehicle->setYear($vData['year']);
            $vehicle->setFuelType($vData['fuel']);
            $vehicle->setColor($vData['color']);
            $vehicle->setMileage($vData['mileage']);
            $vehicle->setNextKteoDate(new \DateTimeImmutable('+6 months'));
            $vehicle->setNextServiceDate(new \DateTimeImmutable('+9 months'));

            $vehicleEntities[] = $vehicle;
        }

        // 6. 9 Repairs (Work Orders) across diverse statuses
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();

        $repairsData = [
            [
                'vehicleIndex' => 0,
                'status' => 'checked_in',
                'description' => 'Annual maintenance service, engine oil & filter replacement',
                'price' => '120.00',
                'odometerKm' => 54000,
                'scheduledTime' => '09:00',
                'date' => $today,
                'checkedInAt' => $now,
                'completedAt' => null,
                'pickedUpAt' => null,
                'notes' => 'Customer requested check of tyre pressures.',
            ],
            [
                'vehicleIndex' => 1,
                'status' => 'checked_in',
                'description' => 'Front brake pads and brake discs replacement',
                'price' => '260.00',
                'odometerKm' => 42000,
                'scheduledTime' => '10:30',
                'date' => $today,
                'checkedInAt' => $now,
                'completedAt' => null,
                'pickedUpAt' => null,
                'notes' => 'Brake wear indicator light on dashboard.',
            ],
            [
                'vehicleIndex' => 2,
                'status' => 'in_progress',
                'description' => 'Clutch kit replacement and manual transmission fluid renewal',
                'price' => '480.00',
                'odometerKm' => 68000,
                'scheduledTime' => '08:30',
                'date' => $today,
                'checkedInAt' => $now->modify('-2 hours'),
                'completedAt' => null,
                'pickedUpAt' => null,
                'notes' => 'Slipping clutch in 3rd and 4th gear.',
            ],
            [
                'vehicleIndex' => 3,
                'status' => 'in_progress',
                'description' => 'Air conditioning diagnostic and refrigerant R1234yf recharge',
                'price' => '95.00',
                'odometerKm' => 28000,
                'scheduledTime' => '11:00',
                'date' => $today,
                'checkedInAt' => $now->modify('-1 hour'),
                'completedAt' => null,
                'pickedUpAt' => null,
                'notes' => 'Cabin cooling efficiency test and pollen filter check.',
            ],
            [
                'vehicleIndex' => 4,
                'status' => 'completed',
                'description' => 'Timing belt kit with water pump & complete coolant flush',
                'price' => '550.00',
                'odometerKm' => 115000,
                'scheduledTime' => '08:00',
                'date' => $today->modify('-1 day'),
                'checkedInAt' => $now->modify('-1 day'),
                'completedAt' => $now->modify('-2 hours'),
                'pickedUpAt' => null,
                'notes' => 'Service completed, ready for customer pickup.',
            ],
            [
                'vehicleIndex' => 5,
                'status' => 'completed',
                'description' => 'Front shock absorbers and control arm bushings replacement',
                'price' => '340.00',
                'odometerKm' => 49000,
                'scheduledTime' => '12:00',
                'date' => $today->modify('-2 days'),
                'checkedInAt' => $now->modify('-2 days'),
                'completedAt' => $now->modify('-1 day'),
                'pickedUpAt' => null,
                'notes' => 'Wheel alignment conducted after suspension install.',
            ],
            [
                'vehicleIndex' => 6,
                'status' => 'delivered',
                'description' => '15,000 km periodic inspection, hybrid health check & cabin filter',
                'price' => '160.00',
                'odometerKm' => 18000,
                'scheduledTime' => '09:30',
                'date' => $today->modify('-3 days'),
                'checkedInAt' => $now->modify('-3 days'),
                'completedAt' => $now->modify('-2 days'),
                'pickedUpAt' => $now->modify('-1 day'),
                'notes' => 'Vehicle handed back in clean condition. Invoice settled.',
            ],
            [
                'vehicleIndex' => 7,
                'status' => 'delivered',
                'description' => 'Alternator replacement and AGM battery test',
                'price' => '380.00',
                'odometerKm' => 132000,
                'scheduledTime' => '14:00',
                'date' => $today->modify('-5 days'),
                'checkedInAt' => $now->modify('-5 days'),
                'completedAt' => $now->modify('-4 days'),
                'pickedUpAt' => $now->modify('-3 days'),
                'notes' => 'New 140A alternator installed with 2-year warranty.',
            ],
            [
                'vehicleIndex' => 8,
                'status' => 'cancelled',
                'description' => 'Exhaust manifold inspection and leak diagnosis',
                'price' => '0.00',
                'odometerKm' => 31000,
                'scheduledTime' => '16:00',
                'date' => $today->modify('-1 day'),
                'checkedInAt' => null,
                'completedAt' => null,
                'pickedUpAt' => null,
                'notes' => 'Customer cancelled appointment due to personal travel.',
            ],
        ];

        foreach ($repairsData as $rData) {
            $vehicle = $vehicleEntities[$rData['vehicleIndex']];
            $customer = $vehicle->getCustomer();

            $existingOrder = $this->workOrderRepository->findOneBy([
                'garage' => $garage,
                'vehicle' => $vehicle,
                'description' => $rData['description'],
            ]);

            if (!$existingOrder instanceof WorkOrder) {
                $order = new WorkOrder();
                $order->setGarage($garage);
                $order->setVehicle($vehicle);
                $order->setCustomer($customer);
                $this->entityManager->persist($order);
            } else {
                $order = $existingOrder;
            }

            $order->setDescription($rData['description']);
            $order->setStatus($rData['status']);
            $order->setPrice($rData['price']);
            $order->setOdometerKm($rData['odometerKm']);
            $order->setScheduledTime($rData['scheduledTime']);
            $order->setDate($rData['date']);
            $order->setCheckedInAt($rData['checkedInAt']);
            $order->setCompletedAt($rData['completedAt']);
            $order->setPickedUpAt($rData['pickedUpAt']);
            $order->setNotes($rData['notes']);

            // Update vehicle last service if completed/delivered
            if (in_array($rData['status'], ['completed', 'delivered'], true)) {
                $vehicle->setLastServiceDate($rData['date']);
                $vehicle->setLastServiceMileage($rData['odometerKm']);
            }
        }

        $this->entityManager->flush();

        $io->success('Basic accounts, Workshop Garage, 10 Customers with Vehicles, and 9 Repairs successfully seeded!');

        // Display summary table
        $table = new Table($io);
        $table->setHeaders(['Entity', 'Details', 'Credentials / Count']);
        $table->setRows([
            ['Super Admin', 'Platform Administration', 'sarriskonst@gmail.com / 12345'],
            ['Garage Owner', 'Workshop Administration', 'sarr-c@hotmail.com / 12345'],
            ['Garage', 'Sarris Auto Service (Athens)', 'Status: active (Trial/Sub: active)'],
            ['Clients (Customers)', 'Linked to Sarris Auto Service', '10 clients'],
            ['Vehicles', '1 per client (Greek plates, specs)', '10 vehicles'],
            ['Repairs (Work Orders)', '2 checked_in, 2 in_progress, 2 completed, 2 delivered, 1 cancelled', '9 repairs'],
        ]);
        $table->render();

        return Command::SUCCESS;
    }
}

