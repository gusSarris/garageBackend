<?php

namespace App\DTO\Garage\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PresetsSettingsRequest
{
    /**
     * @param list<string>|null $quickWorkChips
     * @param list<int|float>|null $quickPricePresets
     */
    public function __construct(
        #[Assert\Count(max: 20, maxMessage: 'quickWorkChips cannot contain more than {{ limit }} items')]
        #[Assert\All([
            new Assert\NotBlank(message: 'quickWorkChip cannot be blank'),
            new Assert\Length(max: 50, maxMessage: 'quickWorkChip cannot exceed {{ limit }} characters'),
        ])]
        public ?array $quickWorkChips = null,

        #[Assert\Count(max: 10, maxMessage: 'quickPricePresets cannot contain more than {{ limit }} items')]
        #[Assert\All([
            new Assert\Positive(message: 'quickPricePreset must be positive'),
        ])]
        public ?array $quickPricePresets = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->quickWorkChips !== null) {
            $data['quickWorkChips'] = array_values(array_map('trim', $this->quickWorkChips));
        }
        if ($this->quickPricePresets !== null) {
            $data['quickPricePresets'] = array_values(array_map('floatval', $this->quickPricePresets));
        }

        return $data;
    }
}
