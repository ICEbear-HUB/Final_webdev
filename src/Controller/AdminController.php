<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    // ADMIN DASHBOARD
    #[Route('/admin', name: 'app_admin')]
    public function index(
        UserRepository $userRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $userRepository->count([]),
            'totalProducts' => $productRepository->count([]),
            'totalCategories' => $categoryRepository->count([]),
        ]);
    }

    // USER MANAGEMENT - READ
    #[Route('/admin/users', name: 'admin_users')]
    public function users(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    // USER MANAGEMENT - CREATE
    #[Route('/admin/users/create', name: 'admin_users_create', methods: ['GET', 'POST'])]
    public function createUser(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {

            if (!$this->isCsrfTokenValid('create_user', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $email = strtolower(trim($request->request->get('email', '')));
            $firstName = trim($request->request->get('firstName', ''));
            $lastName = trim($request->request->get('lastName', ''));
            $password = $request->request->get('password', '');
            $role = $request->request->get('role', 'ROLE_USER');

            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                empty($firstName) ||
                empty($lastName) ||
                strlen($password) < 8 ||
                !in_array($role, ['ROLE_USER', 'ROLE_STAFF'], true)
            ) {
                $this->addFlash('error', 'Please provide valid information. Password must be at least 8 characters.');

                return $this->redirectToRoute('admin_users_create');
            }

            if ($userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'This email address is already registered.');

                return $this->redirectToRoute('admin_users_create');
            }

            $user = new User();

            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPassword(
                $passwordHasher->hashPassword($user, $password)
            );
            $user->setRoles([$role]);
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User successfully created!');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/create.html.twig');
    }
}
