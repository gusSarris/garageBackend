<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * Search customers belonging to a garage with case-insensitive substring matching
     * on phone, lastName, firstName, companyName, or email.
     *
     * @return Customer[]
     */
    public function searchByGarage(\App\Entity\Garage $garage, ?string $query = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.garage = :garage')
            ->setParameter('garage', $garage)
            ->orderBy('c.createdAt', 'DESC');

        if ($query !== null && trim($query) !== '') {
            $term = '%' . mb_strtolower(trim($query)) . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(c.phone) LIKE :term',
                    'LOWER(c.lastName) LIKE :term',
                    'LOWER(c.firstName) LIKE :term',
                    'LOWER(c.companyName) LIKE :term',
                    'LOWER(c.email) LIKE :term'
                )
            )->setParameter('term', $term);
        }

        return $qb->getQuery()->getResult();
    }
}
