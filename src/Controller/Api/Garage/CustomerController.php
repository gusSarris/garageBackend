<?php

namespace App\Controller\Api\Garage;

use App\DTO\Garage\CreateCustomerRequest;
use App\DTO\Garage\UpdateCustomerRequest;
use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage/customers', name: 'api_garage_customers_')]
#[IsGranted('ROLE_MECHANIC')]
final class CustomerController extends AbstractController
{
    public function __construct(
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

        $query = $request->query->get('query');
        $customers = $this->customerRepository->searchByGarage($garage, is_string($query) ? $query : null);

        $data = array_map(fn (Customer $customer) => $this->formatCustomerSummary($customer), $customers);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateCustomerRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $customer = new Customer();
        $customer->setGarage($garage);
        $customer->setPhone($dto->phone);
        $customer->setFirstName($dto->firstName);
        $customer->setLastName($dto->lastName);
        $customer->setCompanyName($dto->companyName);
        $customer->setVatNumber($dto->vatNumber);
        $customer->setTaxOffice($dto->taxOffice);
        $customer->setSecondaryPhone($dto->secondaryPhone);
        $customer->setEmail($dto->email);
        $customer->setAddress($dto->address);
        $customer->setCity($dto->city);
        $customer->setPostalCode($dto->postalCode);
        $customer->setNotes($dto->notes);

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $this->json($this->formatCustomerSummary($customer), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->findCustomerScopedToGarage($id, $garage);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatCustomerDetail($customer));
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(string $id, #[MapRequestPayload] UpdateCustomerRequest $dto): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->findCustomerScopedToGarage($id, $garage);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        if ($dto->phone !== null) {
            $customer->setPhone($dto->phone);
        }
        if ($dto->firstName !== null) {
            $customer->setFirstName($dto->firstName);
        }
        if ($dto->lastName !== null) {
            $customer->setLastName($dto->lastName);
        }
        if ($dto->companyName !== null) {
            $customer->setCompanyName($dto->companyName);
        }
        if ($dto->vatNumber !== null) {
            $customer->setVatNumber($dto->vatNumber);
        }
        if ($dto->taxOffice !== null) {
            $customer->setTaxOffice($dto->taxOffice);
        }
        if ($dto->secondaryPhone !== null) {
            $customer->setSecondaryPhone($dto->secondaryPhone);
        }
        if ($dto->email !== null) {
            $customer->setEmail($dto->email);
        }
        if ($dto->address !== null) {
            $customer->setAddress($dto->address);
        }
        if ($dto->city !== null) {
            $customer->setCity($dto->city);
        }
        if ($dto->postalCode !== null) {
            $customer->setPostalCode($dto->postalCode);
        }
        if ($dto->notes !== null) {
            $customer->setNotes($dto->notes);
        }

        $customer->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json($this->formatCustomerDetail($customer));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $customer = $this->findCustomerScopedToGarage($id, $garage);
        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($customer);
        $this->entityManager->flush();

        return $this->json(['message' => 'Customer successfully deleted'], Response::HTTP_OK);
    }

    private function resolveCurrentGarage(): ?Garage
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getGarage();
    }

    private function findCustomerScopedToGarage(string $id, Garage $garage): ?Customer
    {
        try {
            return $this->customerRepository->findOneBy(['id' => $id, 'garage' => $garage]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatCustomerSummary(Customer $customer): array
    {
        return [
            'id' => $customer->getId()?->toRfc4122(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'companyName' => $customer->getCompanyName(),
            'vatNumber' => $customer->getVatNumber(),
            'taxOffice' => $customer->getTaxOffice(),
            'phone' => $customer->getPhone(),
            'secondaryPhone' => $customer->getSecondaryPhone(),
            'email' => $customer->getEmail(),
            'address' => $customer->getAddress(),
            'city' => $customer->getCity(),
            'postalCode' => $customer->getPostalCode(),
            'notes' => $customer->getNotes(),
            'createdAt' => $customer->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'vehiclesCount' => count($customer->getVehicles()),
        ];
    }

    private function formatCustomerDetail(Customer $customer): array
    {
        $vehicles = [];
        foreach ($customer->getVehicles() as $vehicle) {
            $vehicles[] = [
                'id' => $vehicle->getId()?->toRfc4122(),
                'licensePlate' => $vehicle->getLicensePlate(),
                'make' => $vehicle->getMake(),
                'model' => $vehicle->getModel(),
                'year' => $vehicle->getYear(),
                'mileage' => $vehicle->getMileage(),
                'nextKteoDate' => $vehicle->getNextKteoDate()?->format('Y-m-d'),
                'nextServiceDate' => $vehicle->getNextServiceDate()?->format('Y-m-d'),
            ];
        }

        return [
            'id' => $customer->getId()?->toRfc4122(),
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'companyName' => $customer->getCompanyName(),
            'vatNumber' => $customer->getVatNumber(),
            'taxOffice' => $customer->getTaxOffice(),
            'phone' => $customer->getPhone(),
            'secondaryPhone' => $customer->getSecondaryPhone(),
            'email' => $customer->getEmail(),
            'address' => $customer->getAddress(),
            'city' => $customer->getCity(),
            'postalCode' => $customer->getPostalCode(),
            'notes' => $customer->getNotes(),
            'createdAt' => $customer->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $customer->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'vehicles' => $vehicles,
        ];
    }
}
