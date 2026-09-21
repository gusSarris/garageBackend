<?php

namespace App\DTO\Garage;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateGarageProfileRequest
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $name = null,

        #[Assert\Email(message: 'Invalid garage email format')]
        #[Assert\Length(max: 255)]
        public ?string $email = null,

        #[Assert\Length(max: 50)]
        public ?string $phone = null,

        #[Assert\Length(max: 255)]
        public ?string $address = null,

        #[Assert\Length(max: 100)]
        public ?string $city = null,

        #[Assert\Length(max: 20)]
        public ?string $postalCode = null,
    ) {
    }
}
