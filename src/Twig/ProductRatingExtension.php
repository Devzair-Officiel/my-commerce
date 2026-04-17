<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ReviewRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fournit la fonction Twig `product_rating(productId)` qui retourne
 * la note moyenne et le nombre d'avis approuvés d'un produit.
 *
 * Toutes les notes sont chargées en une seule requête SQL lors du premier
 * appel, puis mises en cache en mémoire pour la durée de la requête HTTP.
 */
final class ProductRatingExtension extends AbstractExtension
{
    /** @var array<int, array{average: float, count: int}>|null null = pas encore chargé */
    private ?array $cache = null;

    public function __construct(private readonly ReviewRepository $reviewRepository) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_rating', $this->getProductRating(...)),
        ];
    }

    /**
     * Retourne les données de notation d'un produit.
     *
     * @return array{average: float, count: int}
     */
    public function getProductRating(int $productId): array
    {
        if ($this->cache === null) {
            $this->cache = $this->reviewRepository->findRatingSummaryForProducts([]);
        }

        return $this->cache[$productId] ?? ['average' => 0.0, 'count' => 0];
    }
}
