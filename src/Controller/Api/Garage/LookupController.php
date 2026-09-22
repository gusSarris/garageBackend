<?php

namespace App\Controller\Api\Garage;

use App\Entity\Customer;
use App\Entity\Garage;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\CustomerRepository;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/garage/lookup', name: 'api_garage_lookup_')]
#[IsGranted('ROLE_MECHANIC')]
final class LookupController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly VehicleRepository $vehicleRepository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function lookup(Request $request): JsonResponse
    {
        $garage = $this->resolveCurrentGarage();
        if (!$garage instanceof Garage) {
            return $this->json(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST);
        }

        $phone = $request->query->get('phone');
        $plate = $request->query->get('plate');

        if (($phone === null || trim($phone) === '') && ($plate === null || trim($plate) === '')) {
            return $this->json(
                ['error' => 'Either phone or plate query parameter must be provided.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $result = [];

        if ($phone !== null && trim($phone) !== '') {
            $customer = $this->findCustomerByPhone($garage, trim($phone));
            if ($customer instanceof Customer) {
                $vehicles = [];
                foreach ($customer->getVehicles() as $vehicle) {
                    $vehicles[] = $this->formatVehicle($vehicle);
                }

                $result['customer'] = $this->formatCustomer($customer);
                $result['vehicles'] = $vehicles;
            } else {
                $result['customer'] = null;
                $result['vehicles'] = [];
            }
        }

        if ($plate !== null && trim($plate) !== '') {
            $vehicle = $this->findVehicleByPlate($garage, trim($plate));
            if ($vehicle instanceof Vehicle) {
                $result['vehicle'] = $this->formatVehicle($vehicle);
                $result['customer'] = $vehicle->getCustomer() instanceof Customer
                    ? $this->formatCustomer($vehicle->getCustomer())
                    : null;
            } else {
                $result['vehicle'] = null;
                if (!array_key_exists('customer', $result)) {
                    $result['customer'] = null;
                }
            }
        }

        return $this->json($result);
    }

    private function resolveCurrentGarage(): ?Garage
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getGarage();
    }

    private function findCustomerByPhone(Garage $garage, string $phone): ?Customer
    {
        $normalizedTarget = $this->normalizePhone($phone);
        if ($normalizedTarget === '') {
            return null;
        }

        // Query customers belonging to this garage
        $customers = $this->customerRepository->findBy(['garage' => $garage]);
        foreach ($customers as $candidate) {
            if ($candidate->getPhone() !== null && $this->normalizePhone($candidate->getPhone()) === $normalizedTarget) {
                return $candidate;
            }
            if ($candidate->getSecondaryPhone() !== null && $this->normalizePhone($candidate->getSecondaryPhone()) === $normalizedTarget) {
                return $candidate;
            }
        }

        return null;
    }

    private function findVehicleByPlate(Garage $garage, string $plate): ?Vehicle
    {
        $normalizedTarget = $this->normalizePlate($plate);
        if ($normalizedTarget === '') {
            return null;
        }

        $vehicles = $this->vehicleRepository->findBy(['garage' => $garage]);
        foreach ($vehicles as $candidate) {
            if ($candidate->getLicensePlate() !== null && $this->normalizePlate($candidate->getLicensePlate()) === $normalizedTarget) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^\d]/', '', trim($phone)) ?? '';
        if (str_starts_with($digits, '0030')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '30') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    private function normalizePlate(string $plate): string
    {
        $clean = preg_replace('/[\s\-_]/u', '', trim($plate)) ?? '';
        $upper = mb_strtoupper($clean);

        $greekToLatin = [
            'Α' => 'A', 'Β' => 'B', 'Ε' => 'E', 'Ζ' => 'Z',
            'Η' => 'H', 'Ι' => 'I', 'Κ' => 'K', 'Μ' => 'M',
            'Ν' => 'N', 'Ο' => 'O', 'Ρ' => 'P', 'Τ' => 'T',
            'Υ' => 'Y', 'Χ' => 'X',
        ];

        return strtr($upper, $greekToLatin);
    }

    private function formatCustomer(Customer $customer): array
    {
        $fullName = trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? ''));
        $name = $fullName !== '' ? $fullName : ($customer->getCompanyName() ?? $customer->getPhone() ?? '');

        return [
            'id' => $customer->getId()?->toRfc4122(),
            'name' => $name,
            'firstName' => $customer->getFirstName(),
            'lastName' => $customer->getLastName(),
            'companyName' => $customer->getCompanyName(),
            'phone' => $customer->getPhone(),
            'secondaryPhone' => $customer->getSecondaryPhone(),
            'email' => $customer->getEmail(),
        ];
    }

    private function formatVehicle(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->getId()?->toRfc4122(),
            'licensePlate' => $vehicle->getLicensePlate(),
            'make' => $vehicle->getMake(),
            'model' => $vehicle->getModel(),
            'year' => $vehicle->getYear(),
        ];
    }
}
