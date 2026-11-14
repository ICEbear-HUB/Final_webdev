<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\StaffProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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

    #[Route('/profile/password', name: 'staff_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->getString('current_password');
            $newPassword = $request->request->getString('new_password');
            $confirmPassword = $request->request->getString('confirm_password');

            if (!$this->isCsrfTokenValid(
                'staff_change_password',
                $request->request->getString('_token')
            )) {
                $this->addFlash('danger', 'Invalid security token. Please try again.');
            } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Your current password is incorrect.');
            } elseif (strlen($newPassword) < 8) {
                $this->addFlash('danger', 'Your new password must contain at least 8 characters.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'The new passwords do not match.');
            } else {
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $newPassword)
                );

                $entityManager->flush();

                $this->addFlash('success', 'Your password has been changed successfully.');

                return $this->redirectToRoute('staff_profile');
            }
        }

        return $this->render('staff/change_password.html.twig');
    }
}
