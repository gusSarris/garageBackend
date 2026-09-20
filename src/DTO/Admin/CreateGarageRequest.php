<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateGarageRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Garage name is required')]
        #[Assert\Length(max: 150)]
        public string $name,

        #[Assert\NotBlank(message: 'Garage email is required')]
        #[Assert\Email(message: 'Invalid garage email format')]
        #[Assert\Length(max: 180)]
        public string $email,

        #[Assert\Length(max: 50)]
        public ?string $phone = null,

        #[Assert\Length(max: 50)]
        public ?string $vatNumber = null,

        #[Assert\Length(max: 255)]
        public ?string $address = null,

        #[Assert\Length(max: 100)]
        public ?string $city = null,

        #[Assert\Length(max: 20)]
        public ?string $postalCode = null,

        #[Assert\NotBlank(message: 'Admin email is required')]
        #[Assert\Email(message: 'Invalid admin email format')]
        #[Assert\Length(max: 180)]
        public string $adminEmail = '',

        #[Assert\NotBlank(message: 'Admin full name is required')]
        #[Assert\Length(max: 150)]
        public string $adminFullName = '',

        #[Assert\NotBlank(message: 'Admin password is required')]
        #[Assert\Length(min: 8, minMessage: 'Admin password must be at least 8 characters')]
        public string $adminPassword = '',
    ) {
    }
}
