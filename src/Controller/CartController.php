<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
final class CartController extends AbstractController
{
    // DISPLAY SHOPPING CART
    #[Route('', name: 'cart_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository
    ): Response {
        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $cartItems = [];
        $total = 0;
        $updatedCart = [];

        foreach ($cart as $productId => $quantity) {
            $product = $productRepository->find($productId);

            // Remove products that no longer exist or have no stock.
            if (!$product || $product->getStock() <= 0) {
                continue;
            }

            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            // Never allow cart quantity to exceed available stock.
            $quantity = min($quantity, $product->getStock());

            $updatedCart[$productId] = $quantity;

            $subtotal = (float) $product->getPrice() * $quantity;
            $total += $subtotal;

            $cartItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        }

        // Save corrected cart quantities to the session.
        $session->set('cart', $updatedCart);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'total' => $total,
        ]);
    }

    // ADD PRODUCT TO CART
    #[Route('/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function add(
        Product $product,
        Request $request
    ): Response {
        $token = $request->request->getString('_token');

        if (!$this->isCsrfTokenValid(
            'cart_add_' . $product->getId(),
            $token
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('customer_product_show', [
                'id' => $product->getId(),
            ]);
        }

        if ($product->getStock() <= 0) {
            $this->addFlash('danger', 'This product is out of stock.');

            return $this->redirectToRoute('customer_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $quantity = filter_var(
            $request->request->get('quantity', 1),
            FILTER_VALIDATE_INT
        );

        if ($quantity === false || $quantity < 1) {
            $this->addFlash('danger', 'Please enter a valid quantity.');

            return $this->redirectToRoute('customer_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        $currentQuantity = (int) ($cart[$product->getId()] ?? 0);

        if ($currentQuantity + $quantity > $product->getStock()) {
            $this->addFlash(
                'danger',
                'You cannot add more than the available stock.'
            );

            return $this->redirectToRoute('customer_product_show', [
                'id' => $product->getId(),
            ]);
        }

        $cart[$product->getId()] = $currentQuantity + $quantity;

        $session->set('cart', $cart);

        $this->addFlash('success', 'Product added to your cart!');

        return $this->redirectToRoute('cart_index');
    }

    // UPDATE CART QUANTITY
    #[Route('/update/{id}', name: 'cart_update', methods: ['POST'])]
    public function update(
        Product $product,
        Request $request
    ): Response {
        $token = $request->request->getString('_token');

        if (!$this->isCsrfTokenValid(
            'cart_update_' . $product->getId(),
            $token
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('cart_index');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        if (!isset($cart[$product->getId()])) {
            return $this->redirectToRoute('cart_index');
        }

        $quantity = filter_var(
            $request->request->get('quantity'),
            FILTER_VALIDATE_INT
        );

        if (
            $quantity === false ||
            $quantity < 1 ||
            $quantity > $product->getStock()
        ) {
            $this->addFlash(
                'danger',
                'Invalid quantity or insufficient stock.'
            );

            return $this->redirectToRoute('cart_index');
        }

        $cart[$product->getId()] = $quantity;

        $session->set('cart', $cart);

        $this->addFlash('success', 'Cart quantity updated.');

        return $this->redirectToRoute('cart_index');
    }

    // REMOVE PRODUCT FROM CART
    #[Route('/remove/{id}', name: 'cart_remove', methods: ['POST'])]
    public function remove(
        Product $product,
        Request $request
    ): Response {
        $token = $request->request->getString('_token');

        if (!$this->isCsrfTokenValid(
            'cart_remove_' . $product->getId(),
            $token
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('cart_index');
        }

        $session = $request->getSession();
        $cart = $session->get('cart', []);

        unset($cart[$product->getId()]);

        $session->set('cart', $cart);

        $this->addFlash('success', 'Product removed from cart.');

        return $this->redirectToRoute('cart_index');
    }
}
