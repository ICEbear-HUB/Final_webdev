<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token
    ): ?Response {
        $roles = $token->getRoleNames();

        // Admin redirect
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('app_admin')
            );
        }

        // Staff redirect
        if (in_array('ROLE_STAFF', $roles, true)) {
            return new RedirectResponse(
                $this->urlGenerator->generate('app_staff')
            );
        }

        // Customer redirect
        return new RedirectResponse(
            $this->urlGenerator->generate('app_home')
        );
    }
}
