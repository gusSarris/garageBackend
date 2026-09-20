<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[ORM\Index(name: 'idx_vehicle_garage_license_plate', columns: ['garage_id', 'license_plate'])]
#[ORM\Index(name: 'idx_vehicle_garage_vin', columns: ['garage_id', 'vin'])]
#[ORM\Index(name: 'idx_vehicle_garage_next_kteo_date', columns: ['garage_id', 'next_kteo_date'])]
#[ORM\Index(name: 'idx_vehicle_garage_next_service_date', columns: ['garage_id', 'next_service_date'])]
#[ORM\Index(name: 'idx_vehicle_customer_id', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_vehicle_garage_created_at', columns: ['garage_id', 'created_at'])]
class Vehicle
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 20)]
    private ?string $licensePlate = null;

    #[ORM\Column(length: 17, nullable: true)]
    private ?string $vin = null;

    #[ORM\Column(length: 100)]
    private ?string $make = null;

    #[ORM\Column(length: 100)]
    private ?string $model = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $engineCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $engineDisplacement = null;

    #[ORM\Column(nullable: true)]
    private ?int $enginePowerHp = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fuelType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $transmission = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $firstRegistrationDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastServiceDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastServiceMileage = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextKteoDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextServiceDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $nextServiceMileage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastKteoReminderSentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastServiceReminderSentAt = null;

    #[ORM\Column]
    private ?bool $allowReminders = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Garage $garage = null;

    #[ORM\ManyToOne(inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Customer $customer = null;

    /**
     * @var Collection<int, WorkOrder>
     */
    #[ORM\OneToMany(targetEntity: WorkOrder::class, mappedBy: 'vehicle', cascade: ['remove'], orphanRemoval: true)]
    private Collection $workOrders;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'vehicle')]
    private Collection $notifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->allowReminders = true;
        $this->workOrders = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLicensePlate(): ?string
    {
        return $this->licensePlate;
    }

    public function setLicensePlate(string $licensePlate): static
    {
        $this->licensePlate = $licensePlate;

        return $this;
    }

    public function getVin(): ?string
    {
        return $this->vin;
    }

    public function setVin(?string $vin): static
    {
        $this->vin = $vin;

        return $this;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    public function setMake(string $make): static
    {
        $this->make = $make;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getEngineCode(): ?string
    {
        return $this->engineCode;
    }

    public function setEngineCode(?string $engineCode): static
    {
        $this->engineCode = $engineCode;

        return $this;
    }

    public function getEngineDisplacement(): ?int
    {
        return $this->engineDisplacement;
    }

    public function setEngineDisplacement(?int $engineDisplacement): static
    {
        $this->engineDisplacement = $engineDisplacement;

        return $this;
    }

    public function getEnginePowerHp(): ?int
    {
        return $this->enginePowerHp;
    }

    public function setEnginePowerHp(?int $enginePowerHp): static
    {
        $this->enginePowerHp = $enginePowerHp;

        return $this;
    }

    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    public function setFuelType(?string $fuelType): static
    {
        $this->fuelType = $fuelType;

        return $this;
    }

    public function getTransmission(): ?string
    {
        return $this->transmission;
    }

    public function setTransmission(?string $transmission): static
    {
        $this->transmission = $transmission;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getFirstRegistrationDate(): ?\DateTimeImmutable
    {
        return $this->firstRegistrationDate;
    }

    public function setFirstRegistrationDate(?\DateTimeImmutable $firstRegistrationDate): static
    {
        $this->firstRegistrationDate = $firstRegistrationDate;

        return $this;
    }

    public function getMileage(): ?int
    {
        return $this->mileage;
    }

    public function setMileage(?int $mileage): static
    {
        $this->mileage = $mileage;

        return $this;
    }

    public function getLastServiceDate(): ?\DateTimeImmutable
    {
        return $this->lastServiceDate;
    }

    public function setLastServiceDate(?\DateTimeImmutable $lastServiceDate): static
    {
        $this->lastServiceDate = $lastServiceDate;

        return $this;
    }

    public function getLastServiceMileage(): ?int
    {
        return $this->lastServiceMileage;
    }

    public function setLastServiceMileage(?int $lastServiceMileage): static
    {
        $this->lastServiceMileage = $lastServiceMileage;

        return $this;
    }

    public function getNextKteoDate(): ?\DateTimeImmutable
    {
        return $this->nextKteoDate;
    }

    public function setNextKteoDate(?\DateTimeImmutable $nextKteoDate): static
    {
        $this->nextKteoDate = $nextKteoDate;

        return $this;
    }

    public function getNextServiceDate(): ?\DateTimeImmutable
    {
        return $this->nextServiceDate;
    }

    public function setNextServiceDate(?\DateTimeImmutable $nextServiceDate): static
    {
        $this->nextServiceDate = $nextServiceDate;

        return $this;
    }

    public function getNextServiceMileage(): ?int
    {
        return $this->nextServiceMileage;
    }

    public function setNextServiceMileage(?int $nextServiceMileage): static
    {
        $this->nextServiceMileage = $nextServiceMileage;

        return $this;
    }

    public function getLastKteoReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastKteoReminderSentAt;
    }

    public function setLastKteoReminderSentAt(?\DateTimeImmutable $lastKteoReminderSentAt): static
    {
        $this->lastKteoReminderSentAt = $lastKteoReminderSentAt;

        return $this;
    }

    public function getLastServiceReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastServiceReminderSentAt;
    }

    public function setLastServiceReminderSentAt(?\DateTimeImmutable $lastServiceReminderSentAt): static
    {
        $this->lastServiceReminderSentAt = $lastServiceReminderSentAt;

        return $this;
    }

    public function isAllowReminders(): ?bool
    {
        return $this->allowReminders;
    }

    public function setAllowReminders(bool $allowReminders): static
    {
        $this->allowReminders = $allowReminders;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    public function getGarage(): ?Garage
    {
        return $this->garage;
    }

    public function setGarage(?Garage $garage): static
    {
        $this->garage = $garage;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

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
            $workOrder->setVehicle($this);
        }

        return $this;
    }

    public function removeWorkOrder(WorkOrder $workOrder): static
    {
        if ($this->workOrders->removeElement($workOrder)) {
            // set the owning side to null (unless already changed)
            if ($workOrder->getVehicle() === $this) {
                $workOrder->setVehicle(null);
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
            $notification->setVehicle($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getVehicle() === $this) {
                $notification->setVehicle(null);
            }
        }

        return $this;
    }
}
