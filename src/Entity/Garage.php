<?php

namespace App\Entity;

use App\Repository\GarageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GarageRepository::class)]
class Garage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $taxOffice = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 50)]
    private ?string $subscriptionStatus = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Customer>
     */
    #[ORM\OneToMany(targetEntity: Customer::class, mappedBy: 'garage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $customers;

    /**
     * @var Collection<int, Vehicle>
     */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'garage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $vehicles;

    /**
     * @var Collection<int, WorkOrder>
     */
    #[ORM\OneToMany(targetEntity: WorkOrder::class, mappedBy: 'garage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $workOrders;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'garage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $users;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'garage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, SmsTemplate>
     */
    #[ORM\OneToMany(targetEntity: SmsTemplate::class, mappedBy: 'garage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $smsTemplates;

    public function __construct()
    {
        $this->customers = new ArrayCollection();
        $this->subscriptionStatus = 'trial';
        $this->isActive = true;
        $this->createdAt = new \DateTimeImmutable();
        $this->vehicles = new ArrayCollection();
        $this->workOrders = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->smsTemplates = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getTaxOffice(): ?string
    {
        return $this->taxOffice;
    }

    public function setTaxOffice(?string $taxOffice): static
    {
        $this->taxOffice = $taxOffice;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getSubscriptionStatus(): ?string
    {
        return $this->subscriptionStatus;
    }

    public function setSubscriptionStatus(string $subscriptionStatus): static
    {
        $this->subscriptionStatus = $subscriptionStatus;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Customer>
     */
    public function getCustomers(): Collection
    {
        return $this->customers;
    }

    public function addCustomer(Customer $customer): static
    {
        if (!$this->customers->contains($customer)) {
            $this->customers->add($customer);
            $customer->setGarage($this);
        }

        return $this;
    }

    public function removeCustomer(Customer $customer): static
    {
        if ($this->customers->removeElement($customer)) {
            // set the owning side to null (unless already changed)
            if ($customer->getGarage() === $this) {
                $customer->setGarage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): static
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles->add($vehicle);
            $vehicle->setGarage($this);
        }

        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): static
    {
        if ($this->vehicles->removeElement($vehicle)) {
            // set the owning side to null (unless already changed)
            if ($vehicle->getGarage() === $this) {
                $vehicle->setGarage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    public function getWorkOrders(): Collection
    {
        return $this->workOrders;
    }

    public function addWorkOrder(WorkOrder $workOrder): static
    {
        if (!$this->workOrders->contains($workOrder)) {
            $this->workOrders->add($workOrder);
            $workOrder->setGarage($this);
        }

        return $this;
    }

    public function removeWorkOrder(WorkOrder $workOrder): static
    {
        if ($this->workOrders->removeElement($workOrder)) {
            // set the owning side to null (unless already changed)
            if ($workOrder->getGarage() === $this) {
                $workOrder->setGarage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setGarage($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getGarage() === $this) {
                $user->setGarage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setGarage($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getGarage() === $this) {
                $notification->setGarage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SmsTemplate>
     */
    public function getSmsTemplates(): Collection
    {
        return $this->smsTemplates;
    }

    public function addSmsTemplate(SmsTemplate $smsTemplate): static
    {
        if (!$this->smsTemplates->contains($smsTemplate)) {
            $this->smsTemplates->add($smsTemplate);
            $smsTemplate->setGarage($this);
        }

        return $this;
    }

    public function removeSmsTemplate(SmsTemplate $smsTemplate): static
    {
        if ($this->smsTemplates->removeElement($smsTemplate)) {
            // set the owning side to null (unless already changed)
            if ($smsTemplate->getGarage() === $this) {
                $smsTemplate->setGarage(null);
            }
        }

        return $this;
    }
}
