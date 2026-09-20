<?php

namespace App\DTO\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateSubscriptionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Subscription status is required')]
        #[Assert\Choice(
            choices: ['trial', 'active', 'past_due', 'cancelled'],
            message: 'Invalid subscription status. Allowed values: trial, active, past_due, cancelled'
        )]
        public string $subscriptionStatus,

        public ?bool $isActive = null,
    ) {
    }
}
