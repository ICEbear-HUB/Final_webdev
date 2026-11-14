<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manage-orders', name: 'manage_orders_')]
final class OrderManagementController extends AbstractController
{
    private const ALLOWED_STATUSES = [
        'Pending',
        'Processing',
        'Completed',
        'Cancelled',
    ];

    private function checkManagementAccess(): void
    {
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            !$this->isGranted('ROLE_STAFF')
        ) {
            throw $this->createAccessDeniedException(
                'You are not authorized to manage orders.'
            );
        }
    }

    // VIEW ALL CUSTOMER ORDERS
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $this->checkManagementAccess();

        $orders = $orderRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );

        return $this->render('order_management/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    // VIEW ORDER DETAILS
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order): Response
    {
        $this->checkManagementAccess();

        return $this->render('order_management/show.html.twig', [
            'order' => $order,
            'statuses' => self::ALLOWED_STATUSES,
        ]);
    }

    // UPDATE ORDER STATUS
    #[Route('/{id}/status', name: 'update_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(
        Request $request,
        Order $order,
        EntityManagerInterface $entityManager
    ): Response {
        $this->checkManagementAccess();

        $token = $request->getPayload()->getString('_token');

        if (!$this->isCsrfTokenValid(
            'update-order-status-' . $order->getId(),
            $token
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('manage_orders_show', [
                'id' => $order->getId(),
            ]);
        }

        $newStatus = $request->getPayload()->getString('status');

        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            $this->addFlash('danger', 'Invalid order status.');

            return $this->redirectToRoute('manage_orders_show', [
                'id' => $order->getId(),
            ]);
        }

        $order->setStatus($newStatus);

        $entityManager->flush();

        $this->addFlash(
            'success',
            'Order status updated successfully!'
        );

        return $this->redirectToRoute('manage_orders_show', [
            'id' => $order->getId(),
        ]);
    }
}
