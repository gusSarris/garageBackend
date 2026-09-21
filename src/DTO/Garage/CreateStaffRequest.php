<?php

namespace App\DTO\Garage;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateStaffRequest
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
        #[Assert\Length(min: 10, max: 4096, minMessage: 'Password must be at least 10 characters long.')]
        #[Assert\NotCompromisedPassword(message: 'This password has been leaked in a data breach. Please choose a different password.')]
        public string $password,

        #[Assert\NotBlank(message: 'Role is required')]
        #[Assert\Choice(
            choices: ['mechanic', 'admin', 'ROLE_MECHANIC', 'ROLE_GARAGE_ADMIN'],
            message: 'Invalid role. Choose from: mechanic, admin.'
        )]
        public string $role = 'mechanic',
    ) {
    }
}
