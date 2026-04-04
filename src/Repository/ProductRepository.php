<?php

declare(strict_types=1);

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
     * Charge un produit par son slug avec ses médias, catégories et produits liés.
     * Remplace findOneBy(['slug' => $slug]) pour éviter les N+1 en Twig.
     */
    public function findOneBySlugWithRelations(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.medias', 'm')->addSelect('m')
            ->leftJoin('p.categories', 'c')->addSelect('c')
            ->leftJoin('p.relatedProducts', 'rp')->addSelect('rp')
            ->leftJoin('rp.medias', 'rpm')->addSelect('rpm')
            ->where('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Produits d'une catégorie avec médias pré-chargés.
     * Remplace getByCategories() sans eager loading.
     */
    public function getByCategories(int $categoryId): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->leftJoin('p.medias', 'm')->addSelect('m')
            ->where('c.id = :category')
            ->setParameter('category', $categoryId)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Best sellers avec médias pré-chargés.
     * Remplace findBy(['isBestSeller' => true]) sans eager loading.
     */
    public function findBestSellersWithMedias(): array
    {
        return $this->findFeaturedWithMedias('isBestSeller');
    }

    /**
     * Charge les produits filtrés par un flag booléen (isBestSeller, isNewArrival, isAvailable…)
     * avec leurs médias en une seule requête.
     *
     * @param 'isBestSeller'|'isNewArrival'|'isAvailable'|'isFeatured'|'isSpecialOffer' $flag
     */
    public function findFeaturedWithMedias(string $flag, string $order = 'DESC'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.medias', 'm')->addSelect('m')
            ->where("p.{$flag} = true")
            ->orderBy('p.id', $order === 'ASC' ? 'ASC' : 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle sur titre et description.
     * Méthode manquante appelée dans ProductController::searchProduct().
     */
    public function search(?string $term, int $limit = 50): array
    {
        if ($term === null || trim($term) === '') {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->leftJoin('p.medias', 'm')->addSelect('m')
            ->where('p.title LIKE :term OR p.description LIKE :term')
            ->andWhere('p.isAvailable = true')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('p.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCategoriesForLayout(array $categoryIds): array
    {
        return $this->createQueryBuilder('p')
            ->select('
            p.id AS id,
            p.title AS title,
            p.slug AS slug,
            p.regular_price AS price,
            c.id AS category_id
        ')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
