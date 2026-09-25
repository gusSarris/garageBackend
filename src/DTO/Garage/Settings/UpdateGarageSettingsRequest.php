<?php

namespace App\DTO\Garage\Settings;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateGarageSettingsRequest
{
    public function __construct(
        #[Assert\Valid]
        public ?RemindersSettingsRequest $reminders = null,

        #[Assert\Valid]
        public ?PermissionsSettingsRequest $permissions = null,

        #[Assert\Valid]
        public ?PresetsSettingsRequest $presets = null,

        #[Assert\Valid]
        public ?MessagingSettingsRequest $messaging = null,

        #[Assert\Valid]
        public ?DisplaySettingsRequest $display = null,
    ) {
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->reminders !== null) {
            $data['reminders'] = $this->reminders->toArray();
        }
        if ($this->permissions !== null) {
            $data['permissions'] = $this->permissions->toArray();
        }
        if ($this->presets !== null) {
            $data['presets'] = $this->presets->toArray();
        }
        if ($this->messaging !== null) {
            $data['messaging'] = $this->messaging->toArray();
        }
        if ($this->display !== null) {
            $data['display'] = $this->display->toArray();
        }

        return $data;
    }
}
