<?php

namespace App\DTO\Garage;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateVehicleRequest
{
    public function __construct(
        #[Assert\Uuid(message: 'Invalid customer UUID format')]
        public ?string $customerId = null,

        #[Assert\Length(max: 20)]
        public ?string $licensePlate = null,

        #[Assert\Length(max: 100)]
        public ?string $make = null,

        #[Assert\Length(max: 100)]
        public ?string $model = null,

        #[Assert\Length(max: 17)]
        public ?string $vin = null,

        #[Assert\Range(min: 1900, max: 2100)]
        public ?int $year = null,

        #[Assert\Length(max: 50)]
        public ?string $engineCode = null,

        #[Assert\PositiveOrZero]
        public ?int $engineDisplacement = null,

        #[Assert\PositiveOrZero]
        public ?int $enginePowerHp = null,

        #[Assert\Length(max: 50)]
        public ?string $fuelType = null,

        #[Assert\Length(max: 50)]
        public ?string $transmission = null,

        #[Assert\Length(max: 50)]
        public ?string $color = null,

        #[Assert\Date(message: 'Invalid first registration date format. Expected Y-m-d.')]
        public ?string $firstRegistrationDate = null,

        #[Assert\PositiveOrZero]
        public ?int $mileage = null,

        #[Assert\Date(message: 'Invalid last service date format. Expected Y-m-d.')]
        public ?string $lastServiceDate = null,

        #[Assert\PositiveOrZero]
        public ?int $lastServiceMileage = null,

        #[Assert\Date(message: 'Invalid next KTEO date format. Expected Y-m-d.')]
        public ?string $nextKteoDate = null,

        #[Assert\Date(message: 'Invalid next service date format. Expected Y-m-d.')]
        public ?string $nextServiceDate = null,

        #[Assert\PositiveOrZero]
        public ?int $nextServiceMileage = null,

        public ?bool $allowReminders = null,

        public ?string $notes = null,
    ) {
    }
}
