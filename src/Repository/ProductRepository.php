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

    public function findActiveProduct(): ?Product
    {
        return $this->findOneBy(['isActive' => true], ['id' => 'ASC']);
    }

    public function getActiveProduct(): Product
    {
        $product = $this->findActiveProduct();
        if (!$product) {
            throw new \RuntimeException('No active product configured.');
        }

        return $product;
    }
}
