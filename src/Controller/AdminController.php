<?php

namespace App\Controller;

use App\Form\StaffProfileType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    // ==========================================
    // ADMIN DASHBOARD
    // ==========================================

    #[Route('/admin', name: 'app_admin')]
    public function index(
        UserRepository $userRepository,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        OrderRepository $orderRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Calculate total completed sales
        $totalSales = $orderRepository->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->where('o.status = :status')
            ->setParameter('status', 'Completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Count staff accounts
        $totalStaff = $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_STAFF%')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [

            // General statistics
            'totalUsers' => $userRepository->count([]),
            'totalStaff' => $totalStaff,
            'totalProducts' => $productRepository->count([]),
            'totalCategories' => $categoryRepository->count([]),

            // Order statistics
            'totalOrders' => $orderRepository->count([]),

            'pendingOrders' => $orderRepository->count([
                'status' => 'Pending',
            ]),

            'processingOrders' => $orderRepository->count([
                'status' => 'Processing',
            ]),

            'completedOrders' => $orderRepository->count([
                'status' => 'Completed',
            ]),

            'cancelledOrders' => $orderRepository->count([
                'status' => 'Cancelled',
            ]),

            'totalSales' => $totalSales,
        ]);
    }

    // ==========================================
    // USER MANAGEMENT - READ
    // ==========================================

    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function users(
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/users/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    // ==========================================
    // USER MANAGEMENT - ENABLE / DISABLE
    // ==========================================

    #[Route(
        '/admin/users/{id}/toggle-status',
        name: 'admin_users_toggle_status',
        methods: ['POST']
    )]
    public function toggleUserStatus(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Find target account
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        // Identify the logged-in administrator
        $currentUser = $this->getUser();

        // Protect own account and other administrator accounts
        if (
            !($currentUser instanceof User) ||
            $user->getId() === $currentUser->getId() ||
            in_array('ROLE_ADMIN', $user->getRoles(), true)
        ) {
            $this->addFlash(
                'error',
                'You cannot change this account status.'
            );

            return $this->redirectToRoute('admin_users');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid(
            'toggle_user_' . $user->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid CSRF token.'
            );
        }

        // Switch account status
        $user->setIsActive(!$user->isActive());

        $entityManager->flush();

        // Display confirmation
        $this->addFlash(
            'success',
            $user->isActive()
                ? 'User account enabled successfully.'
                : 'User account disabled successfully.'
        );

        return $this->redirectToRoute('admin_users');
    }


    // ==========================================
    // ADMIN PROFILE
    // ==========================================

    #[Route('/admin/profile', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(StaffProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Your admin profile has been updated successfully.');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    // ==========================================
    // ADMIN CHANGE PASSWORD
    // ==========================================

    #[Route('/admin/profile/password', name: 'admin_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $currentPassword = $request->request->getString('current_password');
            $newPassword = $request->request->getString('new_password');
            $confirmPassword = $request->request->getString('confirm_password');

            if (!$this->isCsrfTokenValid(
                'admin_change_password',
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

                $this->addFlash('success', 'Your admin password has been changed successfully.');

                return $this->redirectToRoute('admin_profile');
            }
        }

        return $this->render('admin/change_password.html.twig');
    }


    

}

