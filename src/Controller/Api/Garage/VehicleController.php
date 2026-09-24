<?php

namespace App\Controller\Api\Garage;

use App\DTO\Garage\CreateVehicleRequest;
use App\DTO\Garage\UpdateVehicleRequest;
use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use App\Repository\CustomerRepository;
use App\Repository\VehicleRepository;
use App\Repository\WorkOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage/vehicles', name: 'api_garage_vehicles_')]
#[IsGranted('ROLE_MECHANIC')]
final class VehicleController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly CustomerRepository $customerRepository,
        private readonly WorkOrderRepository $workOrderRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $query = $request->query->get('query');
        $customerId = $request->query->get('customer_id');
        $upcomingKteo = $request->query->has('upcoming_kteo') ? (int) $request->query->get('upcoming_kteo') : null;
        $upcomingService = $request->query->has('upcoming_service') ? (int) $request->query->get('upcoming_service') : null;

        $vehicles = $this->vehicleRepository->searchByGarage(
            $garage,
            is_string($query) ? $query : null,
            is_string($customerId) ? $customerId : null,
            $upcomingKteo,
            $upcomingService
        );

        $data = array_map(fn (Vehicle $vehicle) => $this->formatVehicleSummary($vehicle), $vehicles);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateVehicleRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->findCustomerScopedToGarage($dto->customerId, $garage);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        $vehicle = new Vehicle();
        $vehicle->setGarage($garage);
        $vehicle->setCustomer($customer);
        $vehicle->setLicensePlate(strtoupper(trim($dto->licensePlate)));
        $vehicle->setMake(trim($dto->make));
        $vehicle->setModel(trim($dto->model));
        $vehicle->setVin($dto->vin !== null ? strtoupper(trim($dto->vin)) : null);
        $vehicle->setYear($dto->year);
        $vehicle->setEngineCode($dto->engineCode);
        $vehicle->setEngineDisplacement($dto->engineDisplacement);
        $vehicle->setEnginePowerHp($dto->enginePowerHp);
        $vehicle->setFuelType($dto->fuelType);
        $vehicle->setTransmission($dto->transmission);
        $vehicle->setColor($dto->color);
        $vehicle->setFirstRegistrationDate($this->parseDate($dto->firstRegistrationDate));
        $vehicle->setMileage($dto->mileage);
        $vehicle->setLastServiceDate($this->parseDate($dto->lastServiceDate));
        $vehicle->setLastServiceMileage($dto->lastServiceMileage);
        $vehicle->setNextKteoDate($this->parseDate($dto->nextKteoDate));
        $vehicle->setNextServiceDate($this->parseDate($dto->nextServiceDate));
        $vehicle->setNextServiceMileage($dto->nextServiceMileage);
        $vehicle->setAllowReminders($dto->allowReminders);
        $vehicle->setNotes($dto->notes);

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        return $this->json($this->formatVehicleDetail($vehicle), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $vehicle = $this->findVehicleScopedToGarage($id, $garage);
        if (!$vehicle instanceof Vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatVehicleDetail($vehicle));
    }

    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    public function history(string $id, Request $request): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $vehicle = $this->findVehicleScopedToGarage($id, $garage);
        if (!$vehicle instanceof Vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $limit = $request->query->getInt('limit', 20);
        if ($limit <= 0) {
            $limit = 20;
        }

        $excludeActive = $request->query->getBoolean('exclude_active', false);

        $workOrders = $this->workOrderRepository->findServiceHistoryByVehicle(
            $garage,
            $vehicle,
            $limit,
            $excludeActive
        );

        $data = array_map(fn (WorkOrder $workOrder) => $this->formatWorkOrderHistory($workOrder), $workOrders);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateVehicleRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $vehicle = $this->findVehicleScopedToGarage($id, $garage);
        if (!$vehicle instanceof Vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        if ($dto->customerId !== null) {
            $newCustomer = $this->findCustomerScopedToGarage($dto->customerId, $garage);
            if (!$newCustomer instanceof Customer) {
                return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
            }
            $vehicle->setCustomer($newCustomer);
        }

        if ($dto->licensePlate !== null) {
            $vehicle->setLicensePlate(strtoupper(trim($dto->licensePlate)));
        }
        if ($dto->vin !== null) {
            $vehicle->setVin(strtoupper(trim($dto->vin)));
        }
        if ($dto->make !== null) {
            $vehicle->setMake(trim($dto->make));
        }
        if ($dto->model !== null) {
            $vehicle->setModel(trim($dto->model));
        }
        if ($dto->year !== null) {
            $vehicle->setYear($dto->year);
        }
        if ($dto->engineCode !== null) {
            $vehicle->setEngineCode($dto->engineCode);
        }
        if ($dto->engineDisplacement !== null) {
            $vehicle->setEngineDisplacement($dto->engineDisplacement);
        }
        if ($dto->enginePowerHp !== null) {
            $vehicle->setEnginePowerHp($dto->enginePowerHp);
        }
        if ($dto->fuelType !== null) {
            $vehicle->setFuelType($dto->fuelType);
        }
        if ($dto->transmission !== null) {
            $vehicle->setTransmission($dto->transmission);
        }
        if ($dto->color !== null) {
            $vehicle->setColor($dto->color);
        }
        if ($dto->firstRegistrationDate !== null) {
            $vehicle->setFirstRegistrationDate($this->parseDate($dto->firstRegistrationDate));
        }
        if ($dto->mileage !== null) {
            $vehicle->setMileage($dto->mileage);
        }
        if ($dto->lastServiceDate !== null) {
            $vehicle->setLastServiceDate($this->parseDate($dto->lastServiceDate));
        }
        if ($dto->lastServiceMileage !== null) {
            $vehicle->setLastServiceMileage($dto->lastServiceMileage);
        }
        if ($dto->nextKteoDate !== null) {
            $vehicle->setNextKteoDate($this->parseDate($dto->nextKteoDate));
        }
        if ($dto->nextServiceDate !== null) {
            $vehicle->setNextServiceDate($this->parseDate($dto->nextServiceDate));
        }
        if ($dto->nextServiceMileage !== null) {
            $vehicle->setNextServiceMileage($dto->nextServiceMileage);
        }
        if ($dto->allowReminders !== null) {
            $vehicle->setAllowReminders($dto->allowReminders);
        }
        if ($dto->notes !== null) {
            $vehicle->setNotes($dto->notes);
        }

        $vehicle->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->formatVehicleDetail($vehicle));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_GARAGE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $vehicle = $this->findVehicleScopedToGarage($id, $garage);
        if (!$vehicle instanceof Vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $workOrderCount = $this->workOrderRepository->count(['vehicle' => $vehicle, 'garage' => $garage]);
        if ($workOrderCount > 0) {
            return $this->json([
                'error' => 'Δεν είναι δυνατή η διαγραφή οχήματος με καταγεγραμμένο ιστορικό επισκευών.',
                'code' => 'VEHICLE_HAS_WORK_ORDERS',
                'workOrderCount' => $workOrderCount,
            ], Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($vehicle);
        $this->entityManager->flush();

        return $this->json(['message' => 'Vehicle successfully deleted'], Response::HTTP_OK);
    }

    private function resolveCurrentGarage(): ?Garage
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getGarage();
    }

    private function findCustomerScopedToGarage(string $customerId, Garage $garage): ?Customer
    {
        try {
            return $this->customerRepository->findOneBy(['id' => $customerId, 'garage' => $garage]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function findVehicleScopedToGarage(string $id, Garage $garage): ?Vehicle
    {
        try {
            return $this->vehicleRepository->findOneBy(['id' => $id, 'garage' => $garage]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDate(?string $dateStr): ?\DateTimeImmutable
    {
        if ($dateStr === null || trim($dateStr) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($dateStr));

        return $date !== false ? $date : null;
    }

    private function formatVehicleSummary(Vehicle $vehicle): array
    {
        $customer = $vehicle->getCustomer();
        $customerName = '';
        if ($customer instanceof Customer) {
            $fullName = trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? ''));
            $customerName = $fullName !== '' ? $fullName : ($customer->getCompanyName() ?? $customer->getPhone() ?? '');
        }

        return [
            'id' => $vehicle->getId()?->toRfc4122(),
            'licensePlate' => $vehicle->getLicensePlate(),
            'vin' => $vehicle->getVin(),
            'make' => $vehicle->getMake(),
            'model' => $vehicle->getModel(),
            'year' => $vehicle->getYear(),
            'fuelType' => $vehicle->getFuelType(),
            'transmission' => $vehicle->getTransmission(),
            'mileage' => $vehicle->getMileage(),
            'nextKteoDate' => $vehicle->getNextKteoDate()?->format('Y-m-d'),
            'nextServiceDate' => $vehicle->getNextServiceDate()?->format('Y-m-d'),
            'allowReminders' => $vehicle->isAllowReminders(),
            'customer' => [
                'id' => $customer?->getId()?->toRfc4122(),
                'name' => $customerName,
                'phone' => $customer?->getPhone(),
            ],
        ];
    }

    private function formatVehicleDetail(Vehicle $vehicle): array
    {
        $customer = $vehicle->getCustomer();

        return [
            'id' => $vehicle->getId()?->toRfc4122(),
            'licensePlate' => $vehicle->getLicensePlate(),
            'vin' => $vehicle->getVin(),
            'make' => $vehicle->getMake(),
            'model' => $vehicle->getModel(),
            'year' => $vehicle->getYear(),
            'engineCode' => $vehicle->getEngineCode(),
            'engineDisplacement' => $vehicle->getEngineDisplacement(),
            'enginePowerHp' => $vehicle->getEnginePowerHp(),
            'fuelType' => $vehicle->getFuelType(),
            'transmission' => $vehicle->getTransmission(),
            'color' => $vehicle->getColor(),
            'firstRegistrationDate' => $vehicle->getFirstRegistrationDate()?->format('Y-m-d'),
            'mileage' => $vehicle->getMileage(),
            'lastServiceDate' => $vehicle->getLastServiceDate()?->format('Y-m-d'),
            'lastServiceMileage' => $vehicle->getLastServiceMileage(),
            'nextKteoDate' => $vehicle->getNextKteoDate()?->format('Y-m-d'),
            'nextServiceDate' => $vehicle->getNextServiceDate()?->format('Y-m-d'),
            'nextServiceMileage' => $vehicle->getNextServiceMileage(),
            'lastKteoReminderSentAt' => $vehicle->getLastKteoReminderSentAt()?->format(\DateTimeInterface::ATOM),
            'lastServiceReminderSentAt' => $vehicle->getLastServiceReminderSentAt()?->format(\DateTimeInterface::ATOM),
            'allowReminders' => $vehicle->isAllowReminders(),
            'notes' => $vehicle->getNotes(),
            'createdAt' => $vehicle->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $vehicle->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'customer' => [
                'id' => $customer?->getId()?->toRfc4122(),
                'firstName' => $customer?->getFirstName(),
                'lastName' => $customer?->getLastName(),
                'companyName' => $customer?->getCompanyName(),
                'phone' => $customer?->getPhone(),
                'email' => $customer?->getEmail(),
            ],
        ];
    }

    private function formatWorkOrderHistory(WorkOrder $workOrder): array
    {
        $status = $workOrder->getStatus() ?? '';
        $statusLabels = [
            'delivered' => 'Παραδόθηκε',
            'completed' => 'Ολοκληρώθηκε',
            'in_progress' => 'Σε εξέλιξη',
            'checked_in' => 'Παραλαβή',
            'scheduled' => 'Προγραμματισμένο',
            'cancelled' => 'Ακυρώθηκε',
        ];

        return [
            'id' => $workOrder->getId()?->toRfc4122(),
            'date' => $workOrder->getDate()?->format('Y-m-d'),
            'status' => $status,
            'statusLabel' => $statusLabels[$status] ?? ucfirst($status),
            'description' => $workOrder->getDescription(),
            'price' => $workOrder->getPrice(),
            'odometerKm' => $workOrder->getOdometerKm(),
            'notes' => $workOrder->getNotes(),
            'partsNotes' => $workOrder->getPartsNotes(),
            'checkedInAt' => $workOrder->getCheckedInAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $workOrder->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'pickedUpAt' => $workOrder->getPickedUpAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $workOrder->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
