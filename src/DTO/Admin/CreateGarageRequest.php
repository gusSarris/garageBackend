<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateGarageRequest
{
    public string $ownerEmail;
    public string $ownerFullName;

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

        ?string $ownerEmail = null,
        ?string $adminEmail = null,
        ?string $ownerFullName = null,
        ?string $adminFullName = null,
        ?string $adminPassword = null,
    ) {
        $this->ownerEmail = (string) ($ownerEmail ?? $adminEmail ?? '');
        $this->ownerFullName = (string) ($ownerFullName ?? $adminFullName ?? '');
    }

    #[Assert\NotBlank(message: 'Owner email is required')]
    #[Assert\Email(message: 'Invalid owner email format')]
    #[Assert\Length(max: 180)]
    public function getOwnerEmail(): string
    {
        return $this->ownerEmail;
    }

    #[Assert\NotBlank(message: 'Owner full name is required')]
    #[Assert\Length(max: 150)]
    public function getOwnerFullName(): string
    {
        return $this->ownerFullName;
    }
}
