<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Search and filter products for Admin.
     *
     * @return Product[]
     */
    public function findByFilters(
        string $search = '',
        ?int $categoryId = null,
        string $stockStatus = ''
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.id', 'DESC');

        if ($search !== '') {
            $qb->andWhere('LOWER(p.Name) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($categoryId !== null && $categoryId > 0) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        switch ($stockStatus) {
            case 'in_stock':
                $qb->andWhere('p.stock > 10');
                break;

            case 'low_stock':
                $qb->andWhere('p.stock > 0')
                    ->andWhere('p.stock <= 10');
                break;

            case 'out_of_stock':
                $qb->andWhere('p.stock = 0');
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
