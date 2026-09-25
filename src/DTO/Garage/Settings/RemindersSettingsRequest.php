<?php

namespace App\DTO\Garage\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RemindersSettingsRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 365, notInRangeMessage: 'kteoDaysBefore must be between {{ min }} and {{ max }}')]
        public ?int $kteoDaysBefore = null,

        #[Assert\Range(min: 1, max: 60, notInRangeMessage: 'serviceMonthsInterval must be between {{ min }} and {{ max }}')]
        public ?int $serviceMonthsInterval = null,

        #[Assert\Range(min: 500, max: 100000, notInRangeMessage: 'serviceKmInterval must be between {{ min }} and {{ max }}')]
        public ?int $serviceKmInterval = null,

        #[Assert\Type(type: 'bool', message: 'autoRemindersDefault must be a boolean')]
        public ?bool $autoRemindersDefault = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->kteoDaysBefore !== null) {
            $data['kteoDaysBefore'] = $this->kteoDaysBefore;
        }
        if ($this->serviceMonthsInterval !== null) {
            $data['serviceMonthsInterval'] = $this->serviceMonthsInterval;
        }
        if ($this->serviceKmInterval !== null) {
            $data['serviceKmInterval'] = $this->serviceKmInterval;
        }
        if ($this->autoRemindersDefault !== null) {
            $data['autoRemindersDefault'] = $this->autoRemindersDefault;
        }

        return $data;
    }
}
