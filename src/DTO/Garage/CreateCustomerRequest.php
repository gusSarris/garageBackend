<?php

namespace App\DTO\Garage;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCustomerRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Phone is required')]
        #[Assert\Length(max: 50)]
        public string $phone,

        #[Assert\Length(max: 100)]
        public ?string $firstName = null,

        #[Assert\Length(max: 100)]
        public ?string $lastName = null,

        #[Assert\Length(max: 255)]
        public ?string $companyName = null,

        #[Assert\Length(max: 50)]
        public ?string $vatNumber = null,

        #[Assert\Length(max: 100)]
        public ?string $taxOffice = null,

        #[Assert\Length(max: 50)]
        public ?string $secondaryPhone = null,

        #[Assert\Email(message: 'Invalid email format')]
        #[Assert\Length(max: 255)]
        public ?string $email = null,

        #[Assert\Length(max: 255)]
        public ?string $address = null,

        #[Assert\Length(max: 100)]
        public ?string $city = null,

        #[Assert\Length(max: 20)]
        public ?string $postalCode = null,

        public ?string $notes = null,
    ) {
    }
}
