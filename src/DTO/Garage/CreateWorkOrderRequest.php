<?php

namespace App\DTO\Garage;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateWorkOrderRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Vehicle ID is required')]
        #[Assert\Uuid(message: 'Invalid vehicle UUID format')]
        public string $vehicleId,

        #[Assert\NotBlank(message: 'Description is required')]
        #[Assert\Length(min: 3, minMessage: 'Description must be at least {{ limit }} characters long')]
        public string $description,

        #[Assert\Uuid(message: 'Invalid customer UUID format')]
        public ?string $customerId = null,

        #[Assert\Date(message: 'Invalid date format. Expected Y-m-d.')]
        public ?string $date = null,

        #[Assert\Choice(
            choices: ['checked_in', 'in_progress', 'completed', 'delivered', 'cancelled'],
            message: 'The status {{ value }} is invalid.'
        )]
        public ?string $status = 'checked_in',

        #[Assert\Length(max: 10)]
        public ?string $scheduledTime = null,

        #[Assert\Regex(
            pattern: '/^\d+(\.\d{1,2})?$/',
            message: 'Price must be a valid decimal amount (e.g. 120.00)'
        )]
        public ?string $price = '0.00',

        #[Assert\PositiveOrZero]
        public ?int $odometerKm = null,

        public ?string $notes = null,

        public ?string $partsNotes = null,
    ) {
    }
}
