<?php

namespace App\Repository;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countSuperAdmins(): int
    {
        $conn = $this->getEntityManager()->getConnection();

        return (int) $conn->fetchOne("SELECT COUNT(*) FROM app_user WHERE roles::text LIKE '%\"ROLE_SUPER_ADMIN\"%'");
    }

    /**
     * @return User[]
     */
    public function findByGarage(Garage $garage, bool $includeDeleted = false): array
    {
        $filters = $this->getEntityManager()->getFilters();
        if ($includeDeleted && $filters->isEnabled('soft_delete')) {
            $filters->disable('soft_delete');
        }

        try {
            return $this->findBy(['garage' => $garage], ['createdAt' => 'ASC']);
        } finally {
            if ($includeDeleted && !$filters->isEnabled('soft_delete')) {
                $filters->enable('soft_delete');
            }
        }
    }

    public function countActiveAdminsByGarage(Garage $garage): int
    {
        $users = $this->findBy(['garage' => $garage, 'isActive' => true]);
        $count = 0;
        foreach ($users as $user) {
            if (in_array('ROLE_GARAGE_ADMIN', $user->getRoles(), true)) {
                $count++;
            }
        }

        return $count;
    }
}
