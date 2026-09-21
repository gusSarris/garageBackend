<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->checkUser($user);
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->checkUser($user);
    }

    private function checkUser(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isDeleted()) {
            throw new CustomUserMessageAccountStatusException('Your account has been deactivated or deleted.');
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Account is pending activation or has been disabled.');
        }
    }
}
