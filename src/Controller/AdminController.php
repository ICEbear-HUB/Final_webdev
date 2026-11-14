<?php

namespace App\Controller;

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
}
