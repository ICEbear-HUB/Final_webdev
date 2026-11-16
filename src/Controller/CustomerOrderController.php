<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/my-orders', name: 'customer_orders_')]
final class CustomerOrderController extends AbstractController
{
    // DISPLAY ALL ORDERS OF THE LOGGED-IN CUSTOMER
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        OrderRepository $orderRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $roles = $user->getRoles();

        if (
            in_array('ROLE_ADMIN', $roles, true) ||
            in_array('ROLE_STAFF', $roles, true)
        ) {
            throw $this->createAccessDeniedException(
                'Only customers can view their orders.'
            );
        }

        // Retrieve only this customer's orders, newest first.
        $orders = $orderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('customer_order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    // DISPLAY INDIVIDUAL ORDER DETAILS
    #[Route(
        '/{id}',
        name: 'show',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function show(
        int $id,
        OrderRepository $orderRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $roles = $user->getRoles();

        if (
            in_array('ROLE_ADMIN', $roles, true) ||
            in_array('ROLE_STAFF', $roles, true)
        ) {
            throw $this->createAccessDeniedException(
                'Only customers can view their orders.'
            );
        }

        // Customers can only access their own orders.
        $order = $orderRepository->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$order) {
            throw $this->createNotFoundException(
                'Order not found.'
            );
        }

        return $this->render('customer_order/show.html.twig', [
            'order' => $order,
        ]);
    }
}
