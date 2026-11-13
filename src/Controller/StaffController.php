<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
