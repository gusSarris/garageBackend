<?php

namespace App\DTO\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Current password is required.')]
        public string $currentPassword,

        #[Assert\NotBlank(message: 'New password is required.')]
        #[Assert\Length(
            min: 10,
            max: 4096,
            minMessage: 'Password must be at least {{ limit }} characters long.',
            maxMessage: 'Password cannot exceed {{ limit }} characters.'
        )]
        #[Assert\NotCompromisedPassword(skipOnError: true)]
        public string $newPassword,
    ) {
    }
}
