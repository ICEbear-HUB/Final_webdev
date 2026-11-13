<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff')]
final class StaffController extends AbstractController
{
    // STAFF DASHBOARD
    #[Route('', name: 'app_staff', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $products = $productRepository->findAll();

        $lowStockProducts = array_filter(
            $products,
            fn($product) => $product->getStock() <= 10
        );

        return $this->render('staff/dashboard.html.twig', [
            'totalProducts' => count($products),
            'lowStockProducts' => count($lowStockProducts),
        ]);
    }

    // STAFF PRODUCT INVENTORY
    #[Route('/products', name: 'staff_products', methods: ['GET'])]
    public function products(ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('staff/products/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    // ADD OR REMOVE PRODUCT STOCK
    #[Route('/products/{id}/stock', name: 'staff_product_stock_update', methods: ['POST'])]
    public function updateStock(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $token = $request->getPayload()->getString('_token');

        if (!$this->isCsrfTokenValid(
            'update-stock-' . $product->getId(),
            $token
        )) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('staff_products');
        }

        $quantity = filter_var(
            $request->getPayload()->getString('quantity'),
            FILTER_VALIDATE_INT
        );

        $operation = $request->getPayload()->getString('operation');

        if ($quantity === false || $quantity <= 0) {
            $this->addFlash('danger', 'Please enter a quantity greater than zero.');
            return $this->redirectToRoute('staff_products');
        }

        $currentStock = $product->getStock();

        if ($operation === 'add') {
            $newStock = $currentStock + $quantity;
        } elseif ($operation === 'remove') {
            if ($quantity > $currentStock) {
                $this->addFlash('danger', 'Cannot remove more stock than currently available.');
                return $this->redirectToRoute('staff_products');
            }

            $newStock = $currentStock - $quantity;
        } else {
            $this->addFlash('danger', 'Invalid stock operation.');
            return $this->redirectToRoute('staff_products');
        }

        $product->setStock($newStock);
        $entityManager->flush();

        $message = $operation === 'add'
            ? $quantity . ' units added successfully.'
            : $quantity . ' units removed successfully.';

        $this->addFlash('success', $message);

        return $this->redirectToRoute('staff_products');
    }
}
