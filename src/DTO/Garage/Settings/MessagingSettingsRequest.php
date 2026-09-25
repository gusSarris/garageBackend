<?php

namespace App\DTO\Garage\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MessagingSettingsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'smsSenderName cannot be blank')]
        #[Assert\Length(min: 1, max: 50, maxMessage: 'smsSenderName cannot exceed {{ limit }} characters')]
        public ?string $smsSenderName = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->smsSenderName !== null) {
            $data['smsSenderName'] = trim($this->smsSenderName);
        }

        return $data;
    }
}
