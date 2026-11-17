<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminInventoryController extends AbstractController
{
    private const LOW_STOCK_THRESHOLD = 10;

    // ==========================================
    // ADMIN INVENTORY OVERVIEW
    // ==========================================

    #[Route('/admin/inventory', name: 'app_admin_inventory', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $products = $productRepository->findAll();

        $totalStock = 0;
        $lowStockProducts = [];
        $outOfStockProducts = [];

        foreach ($products as $product) {
            $stock = (int) $product->getStock();

            $totalStock += $stock;

            if ($stock <= 0) {
                $outOfStockProducts[] = $product;
            } elseif ($stock <= self::LOW_STOCK_THRESHOLD) {
                $lowStockProducts[] = $product;
            }
        }

        return $this->render('admin_inventory/index.html.twig', [
            'products' => $products,
            'totalProducts' => count($products),
            'totalStock' => $totalStock,
            'lowStockProducts' => $lowStockProducts,
            'lowStockCount' => count($lowStockProducts),
            'outOfStockProducts' => $outOfStockProducts,
            'outOfStockCount' => count($outOfStockProducts),
            'lowStockThreshold' => self::LOW_STOCK_THRESHOLD,
        ]);
    }

    // ==========================================
    // ADMIN STOCK ADJUSTMENT
    // ==========================================

    #[Route(
        '/admin/inventory/{id}/stock',
        name: 'admin_inventory_stock_update',
        methods: ['POST']
    )]
    public function updateStock(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $product = $productRepository->find($id);

        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Product not found.');
        }

        if (!$this->isCsrfTokenValid(
            'admin_stock_' . $product->getId(),
            $request->request->getString('_token')
        )) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $quantity = $request->request->getInt('quantity');
        $operation = $request->request->getString('operation');
        $note = trim($request->request->getString('note'));

        if ($quantity <= 0) {
            $this->addFlash('error', 'Quantity must be greater than zero.');

            return $this->redirectToRoute('app_admin_inventory');
        }

        if (!in_array($operation, ['add', 'remove'], true)) {
            $this->addFlash('error', 'Invalid stock operation.');

            return $this->redirectToRoute('app_admin_inventory');
        }

        $previousStock = (int) $product->getStock();

        if ($operation === 'remove' && $quantity > $previousStock) {
            $this->addFlash(
                'error',
                'Insufficient stock. Available stock: ' . $previousStock
            );

            return $this->redirectToRoute('app_admin_inventory');
        }

        $newStock = $operation === 'add'
            ? $previousStock + $quantity
            : $previousStock - $quantity;

        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $movement = new StockMovement();
        $movement->setProduct($product);
        $movement->setMovementType($operation === 'add' ? 'IN' : 'OUT');
        $movement->setQuantity($quantity);
        $movement->setPreviousStock($previousStock);
        $movement->setNewStock($newStock);
        $movement->setNote($note !== '' ? $note : 'Admin stock adjustment');
        $movement->setCreatedAt(new \DateTimeImmutable());
        $movement->setPerformedBy($currentUser);

        $product->setStock($newStock);

        $entityManager->persist($movement);
        $entityManager->flush();

        $this->addFlash('success', 'Product stock updated successfully.');

        return $this->redirectToRoute('app_admin_inventory');
    }

    // ==========================================
    // ADMIN STOCK MOVEMENT HISTORY
    // ==========================================

    #[Route(
        '/admin/inventory/history',
        name: 'admin_inventory_history',
        methods: ['GET']
    )]
    public function history(
        StockMovementRepository $stockMovementRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $movements = $stockMovementRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            100
        );

        return $this->render('admin_inventory/history.html.twig', [
            'movements' => $movements,
        ]);
    }
}
