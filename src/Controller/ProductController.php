<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/products')]
final class ProductController extends AbstractController
{
    
    // DISPLAY, SEARCH AND FILTER PRODUCTS
    #[Route('', name: 'admin_products_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = trim($request->query->getString('search'));
        $categoryId = $request->query->getInt('category');
        $stockStatus = $request->query->getString('stock');

        // Accept only valid stock filter values
        if (!in_array($stockStatus, [
            '',
            'in_stock',
            'low_stock',
            'out_of_stock',
        ], true)) {
            $stockStatus = '';
        }

        return $this->render('product/index.html.twig', [
            'products' => $productRepository->findByFilters(
                $search,
                $categoryId > 0 ? $categoryId : null,
                $stockStatus
            ),
            'categories' => $categoryRepository->findAll(),
            'search' => $search,
            'selectedCategory' => $categoryId,
            'selectedStock' => $stockStatus,
        ]);
    }


    // CREATE PRODUCT
    #[Route('/new', name: 'admin_products_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $product = new Product();

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product created successfully.');

            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    // VIEW PRODUCT
    #[Route('/{id}', name: 'admin_products_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    // EDIT PRODUCT
    #[Route('/{id}/edit', name: 'admin_products_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Product updated successfully.');

            return $this->redirectToRoute('admin_products_index');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    // DELETE PRODUCT
    #[Route('/{id}', name: 'admin_products_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Product $product,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid(
            'delete' . $product->getId(),
            $request->getPayload()->getString('_token')
        )) {
            $this->addFlash('danger', 'Invalid security token.');

            return $this->redirectToRoute('admin_products_index');
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Product deleted successfully.');

        return $this->redirectToRoute('admin_products_index');
    }











}
