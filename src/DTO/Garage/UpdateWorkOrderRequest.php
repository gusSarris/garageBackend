<?php

namespace App\DTO\Garage;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateWorkOrderRequest
{
    public function __construct(
        #[Assert\Choice(
            choices: ['checked_in', 'in_progress', 'completed', 'delivered', 'cancelled'],
            message: 'The status {{ value }} is invalid.'
        )]
        public ?string $status = null,

        #[Assert\Date(message: 'Invalid date format. Expected Y-m-d.')]
        public ?string $date = null,

        #[Assert\Length(max: 10)]
        public ?string $scheduledTime = null,

        #[Assert\Length(min: 3, minMessage: 'Description must be at least {{ limit }} characters long')]
        public ?string $description = null,

        #[Assert\Regex(
            pattern: '/^\d+(\.\d{1,2})?$/',
            message: 'Price must be a valid decimal amount (e.g. 120.00)'
        )]
        public ?string $price = null,

        #[Assert\PositiveOrZero]
        public ?int $odometerKm = null,

        public ?string $notes = null,

        public ?string $partsNotes = null,

        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            message: 'Invalid checked-in datetime format. Expected ISO-8601 (e.g. 2026-09-21T07:30:00+00:00)'
        )]
        public ?string $checkedInAt = null,

        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            message: 'Invalid completed datetime format. Expected ISO-8601 (e.g. 2026-09-21T07:30:00+00:00)'
        )]
        public ?string $completedAt = null,

        #[Assert\Regex(
            pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            message: 'Invalid picked-up datetime format. Expected ISO-8601 (e.g. 2026-09-21T07:30:00+00:00)'
        )]
        public ?string $pickedUpAt = null,
    ) {
    }
}
