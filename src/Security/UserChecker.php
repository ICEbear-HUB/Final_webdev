
<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements LoginSuccessHandlerInterface, UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isActive() === false) {
            throw new CustomUserMessageAccountStatusException(
                'Your account has been disabled. Please contact the administrator.'
            );
        }
    }

    public function LoginSuccessHandler(UserInterface $user): void
    {
        // No additional checks needed after authentication.
    }
}
