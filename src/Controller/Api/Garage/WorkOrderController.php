<?php

namespace App\Controller\Api\Garage;

use App\DTO\Garage\CreateWorkOrderRequest;
use App\DTO\Garage\UpdateWorkOrderRequest;
use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use App\Repository\CustomerRepository;
use App\Repository\VehicleRepository;
use App\Repository\WorkOrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage/work-orders', name: 'api_garage_work_orders_')]
#[IsGranted('ROLE_MECHANIC')]
final class WorkOrderController extends AbstractController
{
    public function __construct(
        private readonly WorkOrderRepository $workOrderRepository,
        private readonly VehicleRepository $vehicleRepository,
        private readonly CustomerRepository $customerRepository,
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

        $status = $request->query->get('status');
        $customerId = $request->query->get('customer_id');
        $vehicleId = $request->query->get('vehicle_id');
        $date = $request->query->get('date');
        $fromDate = $request->query->get('from_date');
        $toDate = $request->query->get('to_date');
        $query = $request->query->get('query');

        $workOrders = $this->workOrderRepository->searchByGarage(
            $garage,
            is_string($status) ? $status : null,
            is_string($customerId) ? $customerId : null,
            is_string($vehicleId) ? $vehicleId : null,
            is_string($date) ? $date : null,
            is_string($fromDate) ? $fromDate : null,
            is_string($toDate) ? $toDate : null,
            is_string($query) ? $query : null
        );

        $data = array_map(fn (WorkOrder $w) => $this->formatWorkOrderSummary($w), $workOrders);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateWorkOrderRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $vehicle = $this->findVehicleScopedToGarage($dto->vehicleId, $garage);
        if (!$vehicle instanceof Vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        if ($dto->customerId !== null) {
            $customer = $this->findCustomerScopedToGarage($dto->customerId, $garage);
            if (!$customer instanceof Customer) {
                return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
            }
        } else {
            $customer = $vehicle->getCustomer();
        }

        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        // Prevent duplicate active work orders for the same vehicle
        $activeWorkOrder = $this->workOrderRepository->findActiveWorkOrderByVehicle($garage, $vehicle);
        if ($activeWorkOrder instanceof WorkOrder) {
            return $this->json([
                'error' => 'Το όχημα βρίσκεται ήδη στο συνεργείο ή στην ουρά αναμονής με ενεργή εργασία.',
                'code' => 'VEHICLE_ALREADY_ACTIVE',
                'activeWorkOrder' => [
                    'id' => $activeWorkOrder->getId()?->toRfc4122(),
                    'status' => $activeWorkOrder->getStatus(),
                    'description' => $activeWorkOrder->getDescription(),
                    'date' => $activeWorkOrder->getDate()?->format('Y-m-d'),
                    'scheduledTime' => $activeWorkOrder->getScheduledTime(),
                    'checkedInAt' => $activeWorkOrder->getCheckedInAt()?->format(\DateTimeInterface::ATOM),
                ],
            ], Response::HTTP_CONFLICT);
        }

        $workOrder = new WorkOrder();
        $workOrder->setGarage($garage);
        $workOrder->setVehicle($vehicle);
        $workOrder->setCustomer($customer);
        $workOrder->setDescription(trim($dto->description));
        $workOrder->setDate($this->parseDate($dto->date) ?? new \DateTimeImmutable('today'));
        $status = $dto->status ?? 'checked_in';
        $workOrder->setStatus($status);
        $workOrder->setScheduledTime($dto->scheduledTime);
        $workOrder->setPrice($dto->price ?? '0.00');
        $workOrder->setOdometerKm($dto->odometerKm);
        $workOrder->setNotes($dto->notes);
        $workOrder->setPartsNotes($dto->partsNotes);

        if ($status === 'checked_in') {
            $workOrder->setCheckedInAt(new \DateTimeImmutable());
        } elseif ($status === 'completed') {
            $now = new \DateTimeImmutable();
            $workOrder->setCheckedInAt($now);
            $workOrder->setCompletedAt($now);
            $vehicle->setLastServiceDate($workOrder->getDate() ?? new \DateTimeImmutable('today'));
            if ($workOrder->getOdometerKm() !== null) {
                $vehicle->setLastServiceMileage($workOrder->getOdometerKm());
            }
            $vehicle->setUpdatedAt(new \DateTimeImmutable());
        } elseif ($status === 'delivered') {
            $now = new \DateTimeImmutable();
            $workOrder->setCheckedInAt($now);
            $workOrder->setCompletedAt($now);
            $workOrder->setPickedUpAt($now);
        }

        if ($dto->odometerKm !== null) {
            if ($vehicle->getMileage() === null || $dto->odometerKm > $vehicle->getMileage()) {
                $vehicle->setMileage($dto->odometerKm);
                $vehicle->setUpdatedAt(new \DateTimeImmutable());
            }
        }

        try {
            $this->entityManager->persist($workOrder);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $existingActive = $this->workOrderRepository->findActiveWorkOrderByVehicle($garage, $vehicle);

            return $this->json([
                'error' => 'Το όχημα βρίσκεται ήδη στο συνεργείο ή στην ουρά αναμονής με ενεργή εργασία.',
                'code' => 'VEHICLE_ALREADY_ACTIVE',
                'activeWorkOrder' => $existingActive instanceof WorkOrder ? [
                    'id' => $existingActive->getId()?->toRfc4122(),
                    'status' => $existingActive->getStatus(),
                    'description' => $existingActive->getDescription(),
                    'date' => $existingActive->getDate()?->format('Y-m-d'),
                    'scheduledTime' => $existingActive->getScheduledTime(),
                    'checkedInAt' => $existingActive->getCheckedInAt()?->format(\DateTimeInterface::ATOM),
                ] : null,
            ], Response::HTTP_CONFLICT);
        }

        return $this->json($this->formatWorkOrderDetail($workOrder), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $workOrder = $this->findWorkOrderScopedToGarage($id, $garage);
        if (!$workOrder instanceof WorkOrder) {
            return $this->json(['error' => 'Work order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatWorkOrderDetail($workOrder));
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateWorkOrderRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $workOrder = $this->findWorkOrderScopedToGarage($id, $garage);
        if (!$workOrder instanceof WorkOrder) {
            return $this->json(['error' => 'Work order not found'], Response::HTTP_NOT_FOUND);
        }

        if ($workOrder->getStatus() === 'delivered' && !$this->isGranted('ROLE_GARAGE_ADMIN')) {
            if ($dto->price !== null || $dto->odometerKm !== null || $dto->status !== null || $dto->date !== null || $dto->description !== null) {
                return $this->json([
                    'error' => 'Δεν επιτρέπεται η τροποποίηση παραδοθείσας επισκευής από μηχανικό.',
                    'code' => 'DELIVERED_REPAIR_UPDATE_LOCKED',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if ($dto->date !== null) {
            $parsedDate = $this->parseDate($dto->date);
            if ($parsedDate instanceof \DateTimeImmutable) {
                $workOrder->setDate($parsedDate);
            }
        }
        if ($dto->scheduledTime !== null) {
            $workOrder->setScheduledTime($dto->scheduledTime);
        }
        if ($dto->description !== null) {
            $workOrder->setDescription(trim($dto->description));
        }
        if ($dto->price !== null) {
            $workOrder->setPrice($dto->price);
        }
        if ($dto->notes !== null) {
            $workOrder->setNotes($dto->notes);
        }
        if ($dto->partsNotes !== null) {
            $workOrder->setPartsNotes($dto->partsNotes);
        }
        if ($dto->odometerKm !== null) {
            $workOrder->setOdometerKm($dto->odometerKm);
            $vehicle = $workOrder->getVehicle();
            if ($vehicle instanceof Vehicle && ($vehicle->getMileage() === null || $dto->odometerKm > $vehicle->getMileage())) {
                $vehicle->setMileage($dto->odometerKm);
                $vehicle->setUpdatedAt(new \DateTimeImmutable());
            }
        }

        if ($dto->checkedInAt !== null) {
            $workOrder->setCheckedInAt($this->parseDateTime($dto->checkedInAt));
        }
        if ($dto->completedAt !== null) {
            $workOrder->setCompletedAt($this->parseDateTime($dto->completedAt));
        }
        if ($dto->pickedUpAt !== null) {
            $workOrder->setPickedUpAt($this->parseDateTime($dto->pickedUpAt));
        }

        if ($dto->status !== null) {
            $workOrder->setStatus($dto->status);

            if ($dto->status === 'checked_in' && $workOrder->getCheckedInAt() === null) {
                $workOrder->setCheckedInAt(new \DateTimeImmutable());
            }

            if ($dto->status === 'completed') {
                if ($workOrder->getCompletedAt() === null) {
                    $workOrder->setCompletedAt(new \DateTimeImmutable());
                }
                $vehicle = $workOrder->getVehicle();
                if ($vehicle instanceof Vehicle) {
                    $vehicle->setLastServiceDate($workOrder->getDate() ?? new \DateTimeImmutable('today'));
                    if ($workOrder->getOdometerKm() !== null) {
                        $vehicle->setLastServiceMileage($workOrder->getOdometerKm());
                        if ($vehicle->getMileage() === null || $workOrder->getOdometerKm() > $vehicle->getMileage()) {
                            $vehicle->setMileage($workOrder->getOdometerKm());
                        }
                    }
                    $vehicle->setUpdatedAt(new \DateTimeImmutable());
                }
            }

            if ($dto->status === 'delivered') {
                if ($workOrder->getPickedUpAt() === null) {
                    $workOrder->setPickedUpAt(new \DateTimeImmutable());
                }
            }
        }

        $workOrder->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->formatWorkOrderDetail($workOrder));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $workOrder = $this->findWorkOrderScopedToGarage($id, $garage);
        if (!$workOrder instanceof WorkOrder) {
            return $this->json(['error' => 'Work order not found'], Response::HTTP_NOT_FOUND);
        }

        $isHistorical = $workOrder->getStatus() === 'delivered' || $workOrder->getPickedUpAt() !== null;
        if ($isHistorical && !$this->isGranted('ROLE_GARAGE_ADMIN')) {
            return $this->json([
                'error' => 'Μόνο ο διαχειριστής του συνεργείου μπορεί να διαγράψει ιστορικές επισκευές.',
                'code' => 'HISTORICAL_REPAIR_DELETE_FORBIDDEN',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($workOrder);
        $this->entityManager->flush();

        return $this->json(['message' => 'Work order successfully deleted'], Response::HTTP_OK);
    }

    private function resolveCurrentGarage(): ?Garage
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getGarage();
    }

    private function findWorkOrderScopedToGarage(string $id, Garage $garage): ?WorkOrder
    {
        try {
            return $this->workOrderRepository->findOneBy(['id' => $id, 'garage' => $garage]);
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

    private function findCustomerScopedToGarage(string $id, Garage $garage): ?Customer
    {
        try {
            return $this->customerRepository->findOneBy(['id' => $id, 'garage' => $garage]);
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

    private function parseDateTime(?string $dateTimeStr): ?\DateTimeImmutable
    {
        if ($dateTimeStr === null || trim($dateTimeStr) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($dateTimeStr));
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatWorkOrderSummary(WorkOrder $workOrder): array
    {
        $customer = $workOrder->getCustomer();
        $customerName = '';
        if ($customer instanceof Customer) {
            $fullName = trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? ''));
            $customerName = $fullName !== '' ? $fullName : ($customer->getCompanyName() ?? $customer->getPhone() ?? '');
        }

        $vehicle = $workOrder->getVehicle();

        return [
            'id' => $workOrder->getId()?->toRfc4122(),
            'date' => $workOrder->getDate()?->format('Y-m-d'),
            'status' => $workOrder->getStatus(),
            'scheduledTime' => $workOrder->getScheduledTime(),
            'description' => $workOrder->getDescription(),
            'price' => $workOrder->getPrice(),
            'odometerKm' => $workOrder->getOdometerKm(),
            'notes' => $workOrder->getNotes(),
            'partsNotes' => $workOrder->getPartsNotes(),
            'checkedInAt' => $workOrder->getCheckedInAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $workOrder->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'pickedUpAt' => $workOrder->getPickedUpAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $workOrder->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $workOrder->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'customer' => $customer ? [
                'id' => $customer->getId()?->toRfc4122(),
                'name' => $customerName,
                'phone' => $customer->getPhone(),
            ] : null,
            'vehicle' => $vehicle ? [
                'id' => $vehicle->getId()?->toRfc4122(),
                'licensePlate' => $vehicle->getLicensePlate(),
                'make' => $vehicle->getMake(),
                'model' => $vehicle->getModel(),
            ] : null,
        ];
    }

    private function formatWorkOrderDetail(WorkOrder $workOrder): array
    {
        $customer = $workOrder->getCustomer();
        $customerName = '';
        if ($customer instanceof Customer) {
            $fullName = trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? ''));
            $customerName = $fullName !== '' ? $fullName : ($customer->getCompanyName() ?? $customer->getPhone() ?? '');
        }

        $vehicle = $workOrder->getVehicle();

        return [
            'id' => $workOrder->getId()?->toRfc4122(),
            'date' => $workOrder->getDate()?->format('Y-m-d'),
            'status' => $workOrder->getStatus(),
            'scheduledTime' => $workOrder->getScheduledTime(),
            'description' => $workOrder->getDescription(),
            'price' => $workOrder->getPrice(),
            'odometerKm' => $workOrder->getOdometerKm(),
            'notes' => $workOrder->getNotes(),
            'partsNotes' => $workOrder->getPartsNotes(),
            'checkedInAt' => $workOrder->getCheckedInAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $workOrder->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'pickedUpAt' => $workOrder->getPickedUpAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $workOrder->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $workOrder->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'customer' => $customer ? [
                'id' => $customer->getId()?->toRfc4122(),
                'name' => $customerName,
                'phone' => $customer->getPhone(),
                'email' => $customer->getEmail(),
                'companyName' => $customer->getCompanyName(),
            ] : null,
            'vehicle' => $vehicle ? [
                'id' => $vehicle->getId()?->toRfc4122(),
                'licensePlate' => $vehicle->getLicensePlate(),
                'make' => $vehicle->getMake(),
                'model' => $vehicle->getModel(),
                'year' => $vehicle->getYear(),
                'vin' => $vehicle->getVin(),
                'mileage' => $vehicle->getMileage(),
            ] : null,
        ];
    }
}
