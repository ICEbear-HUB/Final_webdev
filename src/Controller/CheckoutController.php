<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CheckoutController extends AbstractController
{
    // DISPLAY CHECKOUT PAGE
    #[Route('/checkout', name: 'checkout_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Prevent staff and administrators from using customer checkout.
        $roles = $user->getRoles();

        if (
            in_array('ROLE_ADMIN', $roles, true) ||
            in_array('ROLE_STAFF', $roles, true)
        ) {
            throw $this->createAccessDeniedException(
                'Only customers can place orders.'
            );
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            $this->addFlash('danger', 'Your cart is empty.');

            return $this->redirectToRoute('cart_index');
        }

        // PREPARE CHECKOUT SUMMARY
        $cartItems = [];
        $totalCents = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $productRepository->find($productId);

            if (!$product || $product->getStock() <= 0) {
                continue;
            }

            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $quantity = min($quantity, $product->getStock());

            $priceCents = (int) round(
                (float) $product->getPrice() * 100
            );

            $subtotalCents = $priceCents * $quantity;
            $totalCents += $subtotalCents;

            $cartItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => number_format(
                    $subtotalCents / 100,
                    2,
                    '.',
                    ''
                ),
            ];
        }

        if (empty($cartItems)) {
            $session->set('cart', []);

            $this->addFlash('danger', 'Your cart has no available products.');

            return $this->redirectToRoute('cart_index');
        }

        $total = number_format($totalCents / 100, 2, '.', '');

        // PROCESS CHECKOUT FORM
        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');

            if (!$this->isCsrfTokenValid('checkout', $token)) {
                $this->addFlash('danger', 'Invalid security token.');

                return $this->redirectToRoute('checkout_index');
            }

            $address = trim(
                $request->request->getString('deliveryAddress')
            );

            $phone = trim(
                $request->request->getString('phone')
            );

            if ($address === '' || $phone === '') {
                $this->addFlash(
                    'danger',
                    'Delivery address and phone number are required.'
                );

                return $this->redirectToRoute('checkout_index');
            }

            if (mb_strlen($address) > 65535 || mb_strlen($phone) > 20) {
                $this->addFlash(
                    'danger',
                    'The delivery address or phone number is too long.'
                );

                return $this->redirectToRoute('checkout_index');
            }

            $connection = $entityManager->getConnection();

            try {
                $connection->beginTransaction();

                $order = new Order();

                $order->setOrderNumber(
                    'ACE-' . strtoupper(bin2hex(random_bytes(6)))
                );

                $order->setStatus('Pending');
                $order->setDeliveryAddress($address);
                $order->setPhone($phone);
                $order->setCreatedAt(new \DateTimeImmutable());
                $order->setUser($user);

                $finalTotalCents = 0;

                // RECHECK STOCK AND CREATE ORDER ITEMS
                foreach ($cart as $productId => $quantity) {
                    $quantity = filter_var(
                        $quantity,
                        FILTER_VALIDATE_INT
                    );

                    if ($quantity === false || $quantity < 1) {
                        throw new \RuntimeException(
                            'Invalid product quantity in your cart.'
                        );
                    }

                    $product = $productRepository->find($productId);

                    if (!$product) {
                        throw new \RuntimeException(
                            'A product in your cart no longer exists.'
                        );
                    }

                    // Lock the product row during checkout.
                    $entityManager->lock(
                        $product,
                        LockMode::PESSIMISTIC_WRITE
                    );

                    if ($product->getStock() < $quantity) {
                        throw new \RuntimeException(
                            'Insufficient stock for ' . $product->getName()
                        );
                    }

                    $price = (string) $product->getPrice();

                    $priceCents = (int) round(
                        (float) $price * 100
                    );

                    $finalTotalCents += $priceCents * $quantity;

                    $orderItem = new OrderItem();

                    $orderItem->setProduct($product);
                    $orderItem->setQuantity($quantity);
                    $orderItem->setPrice($price);

                    $order->addItem($orderItem);

                    //save order item to the database
                    $entityManager->persist($orderItem);

                    // DEDUCT PURCHASED QUANTITY
                    $product->setStock(
                        $product->getStock() - $quantity
                    );
                }

                $order->setTotalAmount(
                    number_format(
                        $finalTotalCents / 100,
                        2,
                        '.',
                        ''
                    )
                );

                $entityManager->persist($order);
                $entityManager->flush();

                $connection->commit();

                // CLEAR CART ONLY AFTER SUCCESSFUL ORDER
                $session->set('cart', []);

                $this->addFlash(
                    'success',
                    'Your order has been placed successfully!'
                );

                return $this->redirectToRoute('checkout_success', [
                    'orderNumber' => $order->getOrderNumber(),
                ]);

            } catch (\Throwable $exception) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }

                $this->addFlash(
                    'danger',
                    $exception instanceof \RuntimeException
                        ? $exception->getMessage()
                        : 'Something went wrong while placing your order. Please try again.'
                );

                return $this->redirectToRoute('checkout_index');
            }
        }

        return $this->render('checkout/index.html.twig', [
            'cartItems' => $cartItems,
            'total' => $total,
            'defaultAddress' => $user->getAddress() ?? '',
            'defaultPhone' => $user->getPhone() ?? '',
        ]);
    }

    // ORDER SUCCESS PAGE
    #[Route(
        '/checkout/success/{orderNumber}',
        name: 'checkout_success',
        methods: ['GET']
    )]
    public function success(
        string $orderNumber,
        OrderRepository $orderRepository
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $order = $orderRepository->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
        ]);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }
}
