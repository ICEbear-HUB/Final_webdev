<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
final class UserController extends AbstractController
{
    // CREATE USER
    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim($user->getEmail()));
            $password = $form->get('plainPassword')->getData();
            $role = $form->get('accountRole')->getData();

            $user->setEmail($email);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $form->addError(new FormError('Please enter a valid email address.'));
            } elseif ($userRepository->findOneBy(['email' => $email])) {
                $form->addError(new FormError('This email is already registered.'));
            } elseif (!is_string($password) || strlen($password) < 8) {
                $form->addError(new FormError('Password must be at least 8 characters.'));
            } else {
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $password)
                );

                $user->setRoles([
                    in_array($role, ['ROLE_USER', 'ROLE_STAFF'], true)
                        ? $role
                        : 'ROLE_USER'
                ]);

                $user->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'User successfully created!');

                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    // VIEW USER
    #[Route('/{id}', name: 'admin_users_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    // EDIT USER
    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Protect administrator accounts from this form.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException(
                'Administrator accounts cannot be edited here.'
            );
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim($user->getEmail()));
            $password = $form->get('plainPassword')->getData();
            $role = $form->get('accountRole')->getData();

            $user->setEmail($email);

            $existingUser = $userRepository->findOneBy(['email' => $email]);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $form->addError(new FormError('Please enter a valid email address.'));
            } elseif ($existingUser && $existingUser->getId() !== $user->getId()) {
                $form->addError(new FormError('This email is already registered.'));
            } else {
                if (is_string($password) && $password !== '') {
                    if (strlen($password) < 8) {
                        $form->addError(new FormError(
                            'Password must be at least 8 characters.'
                        ));
                    } else {
                        $user->setPassword(
                            $passwordHasher->hashPassword($user, $password)
                        );
                    }
                }

                if (count($form->getErrors(true)) === 0) {
                    $user->setRoles([
                        in_array($role, ['ROLE_USER', 'ROLE_STAFF'], true)
                            ? $role
                            : 'ROLE_USER'
                    ]);

                    $entityManager->flush();

                    $this->addFlash('success', 'User successfully updated!');

                    return $this->redirectToRoute('admin_users');
                }
            }
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    // DELETE USER
    #[Route('/{id}', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Never allow this CRUD to delete administrator accounts.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException(
                'Administrator accounts cannot be deleted here.'
            );
        }

        // Prevent an administrator from deleting their own account.
        if ($this->getUser() === $user) {
            throw $this->createAccessDeniedException(
                'You cannot delete your own account.'
            );
        }

        if ($this->isCsrfTokenValid(
            'delete' . $user->getId(),
            $request->getPayload()->getString('_token')
        )) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'User successfully deleted!');
        }

        return $this->redirectToRoute('admin_users');
    }
}
