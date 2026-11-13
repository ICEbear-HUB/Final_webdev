<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\StaffProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff')]
final class StaffProfileController extends AbstractController
{
    #[Route('/profile', name: 'staff_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(StaffProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated successfully.');

            return $this->redirectToRoute('staff_profile');
        }

        return $this->render('staff/profile.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}
