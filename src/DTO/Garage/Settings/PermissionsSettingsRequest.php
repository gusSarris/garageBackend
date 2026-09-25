<?php

namespace App\DTO\Garage\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PermissionsSettingsRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'mecCanTweakPrice cannot be null if permissions section is provided')]
        #[Assert\Type(type: 'bool', message: 'mecCanTweakPrice must be a boolean')]
        public ?bool $mecCanTweakPrice = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->mecCanTweakPrice !== null) {
            $data['mecCanTweakPrice'] = $this->mecCanTweakPrice;
        }

        return $data;
    }
}
