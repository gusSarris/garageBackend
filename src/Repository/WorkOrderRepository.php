<?php

namespace App\Repository;

use App\Entity\Garage;
use App\Entity\Vehicle;
use App\Entity\WorkOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<WorkOrder>
 */
class WorkOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkOrder::class);
    }

    /**
     * @return WorkOrder[]
     */
    public function searchByGarage(
        Garage $garage,
        ?string $status = null,
        ?string $customerId = null,
        ?string $vehicleId = null,
        ?string $date = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $query = null
    ): array {
        $qb = $this->createQueryBuilder('w')
            ->leftJoin('w.customer', 'c')
            ->addSelect('c')
            ->leftJoin('w.vehicle', 'v')
            ->addSelect('v')
            ->andWhere('w.garage = :garage')
            ->setParameter('garage', $garage);

        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('w.status = :status')
                ->setParameter('status', trim($status));
        }

        if ($customerId !== null && trim($customerId) !== '') {
            try {
                $customerUuid = Uuid::fromString(trim($customerId));
                $qb->andWhere('w.customer = :customerUuid')
                    ->setParameter('customerUuid', $customerUuid);
            } catch (\InvalidArgumentException) {
                $qb->andWhere('1 = 0');
            }
        }

        if ($vehicleId !== null && trim($vehicleId) !== '') {
            try {
                $vehicleUuid = Uuid::fromString(trim($vehicleId));
                $qb->andWhere('w.vehicle = :vehicleUuid')
                    ->setParameter('vehicleUuid', $vehicleUuid);
            } catch (\InvalidArgumentException) {
                $qb->andWhere('1 = 0');
            }
        }

        if ($date !== null && trim($date) !== '') {
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
            if ($parsedDate !== false) {
                $qb->andWhere('w.date = :exactDate')
                    ->setParameter('exactDate', $parsedDate);
            }
        }

        if ($fromDate !== null && trim($fromDate) !== '') {
            $parsedFrom = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($fromDate));
            if ($parsedFrom !== false) {
                $qb->andWhere('w.date >= :fromDate')
                    ->setParameter('fromDate', $parsedFrom);
            }
        }

        if ($toDate !== null && trim($toDate) !== '') {
            $parsedTo = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($toDate));
            if ($parsedTo !== false) {
                $qb->andWhere('w.date <= :toDate')
                    ->setParameter('toDate', $parsedTo);
            }
        }

        if ($query !== null && trim($query) !== '') {
            $term = '%' . mb_strtolower(trim($query)) . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(w.description) LIKE :term',
                    'LOWER(w.notes) LIKE :term',
                    'LOWER(w.partsNotes) LIKE :term',
                    'LOWER(v.licensePlate) LIKE :term',
                    'LOWER(c.phone) LIKE :term',
                    'LOWER(c.firstName) LIKE :term',
                    'LOWER(c.lastName) LIKE :term',
                    'LOWER(c.companyName) LIKE :term'
                )
            )->setParameter('term', $term);
        }

        $qb->orderBy('w.date', 'DESC')
            ->addOrderBy('w.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findActiveWorkOrderByVehicle(Garage $garage, Vehicle $vehicle): ?WorkOrder
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.garage = :garage')
            ->andWhere('w.vehicle = :vehicle')
            ->andWhere("w.status NOT IN ('delivered', 'cancelled')")
            ->andWhere('w.pickedUpAt IS NULL')
            ->setParameter('garage', $garage)
            ->setParameter('vehicle', $vehicle)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasActiveWorkOrder(Garage $garage, Vehicle $vehicle): bool
    {
        return $this->findActiveWorkOrderByVehicle($garage, $vehicle) !== null;
    }
}
