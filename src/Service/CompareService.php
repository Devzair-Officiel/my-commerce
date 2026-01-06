<?php

namespace App\Service;

use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CompareService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProductRepository $productRepo
    ) {}

    private function session(): SessionInterface
    {
        $session = $this->requestStack->getSession();

        if (!$session instanceof SessionInterface) {
            // En pratique tu es toujours en HTTP, donc ça ne devrait pas arriver,
            // mais c'est plus robuste (CLI/tests).
            throw new \RuntimeException('Session non disponible.');
        }

        return $session;
    }

    public function getCompare(): array
    {
        $compare = $this->session()->get('compare', []);
        return \is_array($compare) ? $compare : [];
    }

    public function updateCompare(array $compare): void
    {
        $this->session()->set('compare', $compare);
    }

    public function addToCompare(int $productId): void
    {
        $compare = $this->getCompare();

        if (!isset($compare[$productId])) {
            $compare[$productId] = 1;
            $this->updateCompare($compare);
        }
    }

    public function removeToCompare(int $productId): void
    {
        $compare = $this->getCompare();

        if (isset($compare[$productId])) {
            unset($compare[$productId]);
            $this->updateCompare($compare);
        }
    }

    public function clearProductFromCompare(): void
    {
        $this->updateCompare([]);
    }

    public function getCompareDetails(): array
    {
        $compare = $this->getCompare();
        $result = [];

        foreach ($compare as $productId => $quantity) {
            $product = $this->productRepo->find((int) $productId);

            if (!$product) {
                unset($compare[$productId]);
                continue;
            }

            $result[] = [
                'id' => $product->getId(),
                'title' => $product->getTitle(),
                'slug' => $product->getSlug(),
                'image' => $product->getMediaFilenames(),
                'soldePrice' => $product->getSoldePrice(),
                'regularPrice' => $product->getRegularPrice(),
            ];
        }

        $this->updateCompare($compare);

        return $result;
    }
}
