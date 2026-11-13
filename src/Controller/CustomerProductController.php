<?php

namespace App\Controller;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products')]
final class CustomerProductController extends AbstractController
{
    #[Route('/{id}', name: 'customer_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        if ($product->getStock() <= 0) {
            throw $this->createNotFoundException(
                'This product is currently unavailable.'
            );
        }

        return $this->render('product/customer_show.html.twig', [
            'product' => $product,
        ]);
    }
}
