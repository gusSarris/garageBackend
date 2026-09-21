<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AcceptInvitationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Invitation token is required')]
        public string $token,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(min: 10, minMessage: 'Password must be at least 10 characters')]
        #[Assert\NotCompromisedPassword(skipOnError: true)]
        public string $password,

        #[Assert\Length(max: 150)]
        public ?string $fullName = null,
    ) {
    }
}
