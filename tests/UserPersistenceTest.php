<?php

namespace App\Tests;

use App\Entity\Garage;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

class UserPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUserPersistenceWithDefaultsAndUuid(): void
    {
        $garage = new Garage();
        $garage->setName('Athens Central Garage');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@athenscentral.example.com');
        $this->entityManager->persist($garage);

        $user = new User();
        // Check constructor defaults
        $this->assertTrue($user->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertContains('ROLE_MECHANIC', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());

        $email = 'mechanic_' . bin2hex(random_bytes(4)) . '@athenscentral.example.com';
        $user->setEmail($email);
        $user->setFullName('Κώστας Παπαδόπουλος');
        $user->setPhone('6981111111');
        $user->setGarage($garage);

        $hashedPassword = password_hash('Secret123!', PASSWORD_BCRYPT);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->assertNotNull($user->getId());
        $this->assertInstanceOf(Uuid::class, $user->getId());
        $this->assertTrue(Uuid::isValid($user->getId()->toRfc4122()));

        $userId = $user->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(User::class, $userId);
        $this->assertNotNull($retrieved);
        $this->assertSame($email, $retrieved->getEmail());
        $this->assertSame($email, $retrieved->getUserIdentifier());
        $this->assertSame('Κώστας Παπαδόπουλος', $retrieved->getFullName());
        $this->assertSame('6981111111', $retrieved->getPhone());
        $this->assertTrue($retrieved->isActive());
        $this->assertTrue(password_verify('Secret123!', $retrieved->getPassword()));
        $this->assertNotNull($retrieved->getGarage());
        $this->assertTrue($retrieved->getGarage()->getId()->equals($garage->getId()));
    }

    public function testUserSecurityInterfacesAndRoles(): void
    {
        $garage = new Garage();
        $garage->setName('Thessaloniki Moto Hub');
        $garage->setEmail('info_' . bin2hex(random_bytes(4)) . '@thessmoto.example.com');
        $this->entityManager->persist($garage);

        $user = new User();
        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertInstanceOf(PasswordAuthenticatedUserInterface::class, $user);

        $email = 'admin_' . bin2hex(random_bytes(4)) . '@thessmoto.example.com';
        $user->setEmail($email);
        $user->setFullName('Γιώργος Γεωργίου');
        $user->setRoles(['ROLE_GARAGE_ADMIN']);
        $user->setPassword('hashed_dummy_password');
        $user->setGarage($garage);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->getId();
        $this->entityManager->clear();

        $retrieved = $this->entityManager->find(User::class, $userId);
        $this->assertNotNull($retrieved);
        // getRoles always guarantees ROLE_USER is included
        $this->assertContains('ROLE_GARAGE_ADMIN', $retrieved->getRoles());
        $this->assertContains('ROLE_USER', $retrieved->getRoles());
        $this->assertSame($email, $retrieved->getUserIdentifier());

        // Test eraseCredentials does not throw
        $retrieved->eraseCredentials();
    }

    public function testCascadeRemovalWhenGarageIsDeleted(): void
    {
        $garage = new Garage();
        $garage->setName('Patras Workshop');
        $garage->setEmail('contact_' . bin2hex(random_bytes(4)) . '@patrasworkshop.example.com');
        $this->entityManager->persist($garage);

        $user1 = new User();
        $user1->setEmail('user1_' . bin2hex(random_bytes(4)) . '@patrasworkshop.example.com');
        $user1->setFullName('Νίκος Αντωνίου');
        $user1->setPassword('password123');
        $garage->addUser($user1);
        $this->entityManager->persist($user1);

        $user2 = new User();
        $user2->setEmail('user2_' . bin2hex(random_bytes(4)) . '@patrasworkshop.example.com');
        $user2->setFullName('Μιχάλης Δημητρίου');
        $user2->setPassword('password456');
        $garage->addUser($user2);
        $this->entityManager->persist($user2);

        $this->entityManager->flush();

        $garageId = $garage->getId();
        $user1Id = $user1->getId();
        $user2Id = $user2->getId();

        $this->entityManager->clear();

        $retrievedGarage = $this->entityManager->find(Garage::class, $garageId);
        $this->assertNotNull($retrievedGarage);
        $this->assertCount(2, $retrievedGarage->getUsers());

        $this->entityManager->remove($retrievedGarage);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Both garage and its users must be deleted
        $this->assertNull($this->entityManager->find(Garage::class, $garageId));
        $this->assertNull($this->entityManager->find(User::class, $user1Id));
        $this->assertNull($this->entityManager->find(User::class, $user2Id));
    }

    public function testGarageUsersAddAndRemoveMethods(): void
    {
        $garage = new Garage();
        $user = new User();

        $garage->addUser($user);
        $this->assertTrue($garage->getUsers()->contains($user));
        $this->assertSame($garage, $user->getGarage());

        $garage->removeUser($user);
        $this->assertFalse($garage->getUsers()->contains($user));
        $this->assertNull($user->getGarage());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}

