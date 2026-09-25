<?php

namespace App\DTO\Garage\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DisplaySettingsRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'darkMode cannot be null if display section is provided')]
        #[Assert\Type(type: 'bool', message: 'darkMode must be a boolean')]
        public ?bool $darkMode = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->darkMode !== null) {
            $data['darkMode'] = $this->darkMode;
        }

        return $data;
    }
}
