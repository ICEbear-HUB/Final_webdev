<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/categories')]
final class CategoryController extends AbstractController
{
    // DISPLAY ALL CATEGORIES
    #[Route('', name: 'admin_categories_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    // CREATE CATEGORY
    #[Route('/new', name: 'admin_categories_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $category = new Category();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Category created successfully.');

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    // VIEW CATEGORY
    #[Route('/{id}', name: 'admin_categories_show', methods: ['GET'])]
    public function show(Category $category): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    // EDIT CATEGORY
    #[Route('/{id}/edit', name: 'admin_categories_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Category $category,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Category updated successfully.');

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    // DELETE CATEGORY
    #[Route('/{id}', name: 'admin_categories_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Category $category,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid(
            'delete' . $category->getId(),
            $request->getPayload()->getString('_token')
        )) {
            $entityManager->remove($category);
            $entityManager->flush();

            $this->addFlash('success', 'Category deleted successfully.');
        }

        return $this->redirectToRoute('admin_categories_index');
    }
}
