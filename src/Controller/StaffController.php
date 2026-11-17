<?php

namespace App\Controller;

use App\Entity\StockMovement;
use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/staff')]
final class StaffController extends AbstractController
{
    // STAFF DASHBOARD
    #[Route('', name: 'app_staff', methods: ['GET'])]
    public function index(
        ProductRepository $productRepository,
        OrderRepository $orderRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $products = $productRepository->findAll();

        $lowStockProducts = array_filter(
            $products,
            fn($product) => $product->getStock() <= 10
        );

        $totalSales = $orderRepository->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->where('o.status = :status')
            ->setParameter('status', 'Completed')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('staff/dashboard.html.twig', [
            'totalProducts' => count($products),
            'lowStockProducts' => count($lowStockProducts),
            'totalOrders' => $orderRepository->count([]),
            'pendingOrders' => $orderRepository->count(['status' => 'Pending']),
            'processingOrders' => $orderRepository->count(['status' => 'Processing']),
            'completedOrders' => $orderRepository->count(['status' => 'Completed']),
            'cancelledOrders' => $orderRepository->count(['status' => 'Cancelled']),
            'totalSales' => $totalSales,
        ]);
    }

    // VIEW ALL SHARED PRODUCTS
    #[Route('/products', name: 'staff_products', methods: ['GET'])]
    public function products(ProductRepository $productRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('staff/products/index.html.twig', [
            'products' => $productRepository->findAll(),
        ]);
    }

    // CREATE PRODUCT
    #[Route('/products/new', name: 'staff_products_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }

            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('products_directory'),
                        $newFilename
                    );
                    $product->setImage('uploads/products/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Failed to upload image file.');
                }
            }

            $product->setCreatedAt(new \DateTimeImmutable());
            $product->setCreatedBy($user);

            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product created successfully.');

            return $this->redirectToRoute('staff_products');
        }

        return $this->render('staff/products/new.html.twig', [
            'form' => $form,
        ]);
    }

    // EDIT OWN PRODUCT ONLY
    #[Route('/products/{id}/edit', name: 'staff_products_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        $this->denyUnlessProductOwner($product);

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('products_directory'),
                        $newFilename
                    );
                    $product->setImage('uploads/products/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Failed to upload image file.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Your product was updated successfully.');

            return $this->redirectToRoute('staff_products');
        }

        return $this->render('staff/products/edit.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    // DELETE OWN PRODUCT ONLY
    #[Route('/products/{id}/delete', name: 'staff_products_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        $this->denyUnlessProductOwner($product);

        $token = $request->getPayload()->getString('_token');

        if (!$this->isCsrfTokenValid(
            'delete_staff_product_' . $product->getId(),
            $token
        )) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('staff_products');
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Your product was deleted successfully.');

        return $this->redirectToRoute('staff_products');
    }

    
// ADD OR REMOVE STOCK - OWN PRODUCTS ONLY
#[Route('/products/{id}/stock', name: 'staff_product_stock_update', methods: ['POST'])]
public function updateStock(
    Request $request,
    Product $product,
    EntityManagerInterface $entityManager
): Response {
    $this->denyAccessUnlessGranted('ROLE_STAFF');
    $this->denyUnlessProductOwner($product);

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
        $movementType = 'IN';
    } elseif ($operation === 'remove') {
        if ($quantity > $currentStock) {
            $this->addFlash('danger', 'Cannot remove more stock than available.');
            return $this->redirectToRoute('staff_products');
        }

        $newStock = $currentStock - $quantity;
        $movementType = 'OUT';
    } else {
        $this->addFlash('danger', 'Invalid stock operation.');
        return $this->redirectToRoute('staff_products');
    }

    $user = $this->getUser();

    if (!$user instanceof User) {
        throw $this->createAccessDeniedException();
    }

    $movement = new StockMovement();
    $movement->setProduct($product);
    $movement->setMovementType($movementType);
    $movement->setQuantity($quantity);
    $movement->setPreviousStock($currentStock);
    $movement->setNewStock($newStock);
    $movement->setNote('Staff stock adjustment');
    $movement->setCreatedAt(new \DateTimeImmutable());
    $movement->setPerformedBy($user);

    $product->setStock($newStock);

    $entityManager->persist($movement);
    $entityManager->flush();

    $this->addFlash(
        'success',
        $operation === 'add'
            ? $quantity . ' units added successfully.'
            : $quantity . ' units removed successfully.'
    );

    return $this->redirectToRoute('staff_products');
}


    // CHECK PRODUCT OWNERSHIP
    private function denyUnlessProductOwner(Product $product): void
    {
        $user = $this->getUser();

        if (
            !$user instanceof User ||
            $product->getCreatedBy()?->getId() !== $user->getId()
        ) {
            throw $this->createAccessDeniedException(
                'You can only manage products created by your account.'
            );
        }
    }
}
