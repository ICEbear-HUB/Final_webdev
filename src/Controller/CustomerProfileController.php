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

#[Route('/customer')]
final class CustomerProfileController extends AbstractController
{
    #[Route('/profile', name: 'customer_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(StaffProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated successfully.');

            return $this->redirectToRoute('customer_profile');
        }

        return $this->render('customer_profile/index.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/profile/password', name: 'customer_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $token = $request->getPayload()->getString('_token');

            if (!$this->isCsrfTokenValid('customer_change_password', $token)) {
                $this->addFlash('danger', 'Invalid security token.');

                return $this->redirectToRoute('customer_change_password');
            }

            $currentPassword = $request->getPayload()->getString('current_password');
            $newPassword = $request->getPayload()->getString('new_password');
            $confirmPassword = $request->getPayload()->getString('confirm_password');

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('danger', 'Your current password is incorrect.');

                return $this->redirectToRoute('customer_change_password');
            }

            if (strlen($newPassword) < 8) {
                $this->addFlash('danger', 'Password must be at least 8 characters long.');

                return $this->redirectToRoute('customer_change_password');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'New passwords do not match.');

                return $this->redirectToRoute('customer_change_password');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $entityManager->flush();

            $this->addFlash('success', 'Your password has been changed successfully.');

            return $this->redirectToRoute('customer_profile');
        }

        return $this->render('customer_profile/change_password.html.twig');
    }
}
