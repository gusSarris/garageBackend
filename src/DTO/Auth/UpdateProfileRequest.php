<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProfileRequest
{
    public function __construct(
        #[Assert\Length(min: 2, max: 150)]
        public ?string $fullName = null,

        #[Assert\Email(message: 'Invalid email format')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
    ) {
    }
}
