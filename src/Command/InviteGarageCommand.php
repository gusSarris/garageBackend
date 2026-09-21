<?php

namespace App\Command;

use App\DTO\Admin\CreateGarageRequest;
use App\Service\GarageProvisioner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsCommand(
    name: 'app:invite-garage',
    description: 'Provisions a new garage tenant and generates an owner activation link',
)]
class InviteGarageCommand
{
    public function __construct(
        private readonly GarageProvisioner $garageProvisioner,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Workshop / Garage name')] string $name,
        #[Argument('Contact email of the garage')] string $email,
        #[Argument('Owner login email address')] string $ownerEmail,
        #[Argument('Full name of the workshop owner')] string $ownerFullName,
        #[Option('Phone number')] ?string $phone = null,
        #[Option('VAT Number')] ?string $vat = null,
        #[Option('Street address')] ?string $address = null,
        #[Option('City')] ?string $city = null,
        #[Option('Postal code')] ?string $postalCode = null,
    ): int {
        try {
            $dto = new CreateGarageRequest(
                name: $name,
                email: $email,
                phone: $phone,
                vatNumber: $vat,
                address: $address,
                city: $city,
                postalCode: $postalCode,
                ownerEmail: $ownerEmail,
                ownerFullName: $ownerFullName,
            );

            $result = $this->garageProvisioner->provision($dto);

            $io->success(sprintf('Garage "%s" created successfully!', $name));
            $io->section('Invitation Details');
            $io->text(sprintf('Owner: %s (%s)', $ownerFullName, $ownerEmail));
            $io->text(sprintf('Activation URL: %s', $result['invitation']['invitationUrl']));
            $io->text(sprintf('Expires At: %s', $result['invitation']['expiresAt']));

            return Command::SUCCESS;
        } catch (HttpExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
