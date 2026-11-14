<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect users who are already logged in.
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin');
            }

            if ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_staff');
            }

            return $this->redirectToRoute('app_home');
        }

        // Get the login error.
        $error = $authenticationUtils->getLastAuthenticationError();

        // Get the last username entered.
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }
}
