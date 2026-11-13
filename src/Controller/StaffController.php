<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StaffController extends AbstractController
{
    #[Route('/staff', name: 'app_staff')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('staff/dashboard.html.twig');
    }
}
