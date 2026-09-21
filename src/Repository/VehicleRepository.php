<?php

namespace App\Repository;

use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    /**
     * @return Vehicle[]
     */
    public function searchByGarage(
        \App\Entity\Garage $garage,
        ?string $query = null,
        ?string $customerId = null,
        ?int $upcomingKteoDays = null,
        ?int $upcomingServiceDays = null
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.customer', 'c')
            ->addSelect('c')
            ->andWhere('v.garage = :garage')
            ->setParameter('garage', $garage);

        if ($query !== null && trim($query) !== '') {
            $term = '%' . mb_strtolower(trim($query)) . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(v.licensePlate) LIKE :term',
                    'LOWER(v.vin) LIKE :term',
                    'LOWER(v.make) LIKE :term',
                    'LOWER(v.model) LIKE :term'
                )
            )->setParameter('term', $term);
        }

        if ($customerId !== null && trim($customerId) !== '') {
            try {
                $customerUuid = \Symfony\Component\Uid\Uuid::fromString(trim($customerId));
                $qb->andWhere('v.customer = :customerUuid')
                    ->setParameter('customerUuid', $customerUuid);
            } catch (\InvalidArgumentException) {
                $qb->andWhere('1 = 0');
            }
        }

        if ($upcomingKteoDays !== null) {
            $today = new \DateTimeImmutable('today');
            $deadline = $today->modify('+' . $upcomingKteoDays . ' days');
            $qb->andWhere('v.nextKteoDate IS NOT NULL AND v.nextKteoDate <= :kteoDeadline')
                ->setParameter('kteoDeadline', $deadline);
        }

        if ($upcomingServiceDays !== null) {
            $today = new \DateTimeImmutable('today');
            $deadline = $today->modify('+' . $upcomingServiceDays . ' days');
            $qb->andWhere('v.nextServiceDate IS NOT NULL AND v.nextServiceDate <= :serviceDeadline')
                ->setParameter('serviceDeadline', $deadline);
        }

        if ($upcomingKteoDays !== null) {
            $qb->orderBy('v.nextKteoDate', 'ASC');
        } elseif ($upcomingServiceDays !== null) {
            $qb->orderBy('v.nextServiceDate', 'ASC');
        } else {
            $qb->orderBy('v.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
