<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateGarageUserRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        #[Assert\Length(max: 180)]
        public string $email,

        #[Assert\NotBlank(message: 'Full name is required')]
        #[Assert\Length(min: 2, max: 150)]
        public string $fullName,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
        public string $password,

        #[Assert\NotBlank(message: 'Role is required')]
        #[Assert\Choice(
            choices: ['admin', 'mechanic', 'ROLE_GARAGE_ADMIN', 'ROLE_MECHANIC'],
            message: 'Invalid role. Choose from: admin, mechanic, ROLE_GARAGE_ADMIN, ROLE_MECHANIC'
        )]
        public string $role = 'mechanic',
    ) {
    }
}
