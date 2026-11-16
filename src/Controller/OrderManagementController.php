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
    public function index(Request $request, OrderRepository $orderRepository): Response
    {
        $this->checkManagementAccess();

        $statusFilter = trim($request->query->getString('status'));
        $search = trim($request->query->getString('q'));

        $qb = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->orderBy('o.createdAt', 'DESC');

        if ($statusFilter !== '' && in_array($statusFilter, Order::ALLOWED_STATUSES, true)) {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $statusFilter);
        }

        if ($search !== '') {
            $qb->andWhere('o.orderNumber LIKE :search OR u.email LIKE :search OR o.phone LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $orders = $qb->getQuery()->getResult();

        $statusCounts = [
            'All' => $orderRepository->count([]),
            Order::STATUS_PENDING => $orderRepository->count(['status' => Order::STATUS_PENDING]),
            Order::STATUS_PROCESSING => $orderRepository->count(['status' => Order::STATUS_PROCESSING]),
            Order::STATUS_COMPLETED => $orderRepository->count(['status' => Order::STATUS_COMPLETED]),
            Order::STATUS_CANCELLED => $orderRepository->count(['status' => Order::STATUS_CANCELLED]),
        ];

        return $this->render('order_management/index.html.twig', [
            'orders' => $orders,
            'selectedStatus' => $statusFilter,
            'searchQuery' => $search,
            'statusCounts' => $statusCounts,
        ]);
    }

    // VIEW ORDER DETAILS
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order): Response
    {
        $this->checkManagementAccess();

        return $this->render('order_management/show.html.twig', [
            'order' => $order,
            'nextStatuses' => $order->getNextAllowedStatuses(),
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

        $newStatus = trim($request->getPayload()->getString('status'));
        $currentStatus = (string) $order->getStatus();

        if ($newStatus === $currentStatus) {
            $this->addFlash('info', 'Order is already in ' . $newStatus . ' status.');

            return $this->redirectToRoute('manage_orders_show', [
                'id' => $order->getId(),
            ]);
        }

        if (!$order->canTransitionTo($newStatus)) {
            $this->addFlash(
                'danger',
                sprintf(
                    'Cannot change order status from "%s" to "%s". Order progression is strictly one-way and cannot be reverted or skipped.',
                    $currentStatus,
                    $newStatus
                )
            );

            return $this->redirectToRoute('manage_orders_show', [
                'id' => $order->getId(),
            ]);
        }

        $order->setStatus($newStatus);

        $entityManager->flush();

        $this->addFlash(
            'success',
            sprintf('Order status updated from "%s" to "%s" successfully!', $currentStatus, $newStatus)
        );

        return $this->redirectToRoute('manage_orders_show', [
            'id' => $order->getId(),
        ]);
    }
}
