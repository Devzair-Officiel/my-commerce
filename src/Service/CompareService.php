<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gère la liste de comparaison de produits stockée en session (ajout, retrait, récupération des détails).
 */
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

    /**
     * Retourne les produits en comparaison enrichis (fiche + labo),
     * prêts pour un rendu Twig serveur.
     *
     * @return array{products: list<array<string, mixed>>, labRows: array<string, bool>, count: int}
     */
    public function getCompareDetails(): array
    {
        $compare = $this->getCompare();
        $products = [];

        foreach (array_keys($compare) as $productId) {
            $product = $this->productRepo->find((int) $productId);

            if (!$product instanceof Product) {
                unset($compare[$productId]);
                continue;
            }

            $effectivePrice = $product->isOnSale()
                ? (int) $product->getSoldePrice()
                : (int) $product->getRegularPrice();

            $coverFilename = $product->getCoverFilename();
            $coverThumb    = $coverFilename !== null
                ? \pathinfo($coverFilename, \PATHINFO_FILENAME) . '-thumb.webp'
                : null;

            $products[] = [
                'id'                => $product->getId(),
                'title'             => $product->getTitle(),
                'slug'              => $product->getSlug(),
                'coverFilename'     => $coverFilename,
                'coverThumb'        => $coverThumb,
                'isOnSale'          => $product->isOnSale(),
                'soldePrice'        => $product->getSoldePrice(),
                'regularPrice'      => $product->getRegularPrice(),
                'effectivePrice'    => $effectivePrice,
                'stock'             => $product->getStock(),
                'isInStock'         => $product->isInStock(),
                'weightGrams'       => $product->getWeightGrams(),
                'originCountry'     => $product->getOriginCountry(),
                'textureLabel'      => $product->getTextureLabel(),
                'colorLabel'        => $product->getColorLabel(),
                'aromaticNotes'     => $product->getAromaticNotes(),
                'tastingSuggestion' => $product->getTastingSuggestion(),
                'intensityLabel'    => $product->getTastingIntensityLabel(),
                'lab'               => $this->extractLabAnalysis($product),
            ];
        }

        $this->updateCompare($compare);

        return [
            'products' => $products,
            'labRows'  => $this->computeLabRowsVisibility($products),
            'count'    => \count($products),
        ];
    }

    /**
     * Détermine quelles lignes labo doivent apparaître (au moins un produit renseigné).
     * Les lignes toujours affichées (humidité, conductivité, pH) restent à la charge du template.
     *
     * @param list<array<string, mixed>> $products
     * @return array{mgo: bool, npa: bool, dha: bool, hmf: bool, pollen: bool, classification: bool}
     */
    private function computeLabRowsVisibility(array $products): array
    {
        $has = ['mgo' => false, 'npa' => false, 'dha' => false, 'hmf' => false, 'pollen' => false, 'classification' => false];

        foreach ($products as $p) {
            $lab = $p['lab'] ?? null;
            if (!\is_array($lab)) continue;

            foreach (['mgo', 'npa', 'dha', 'hmf'] as $key) {
                if (isset($lab[$key]['value']) && $lab[$key]['value'] !== null) {
                    $has[$key] = true;
                }
            }
            if (!empty($lab['pollenDominant']))    $has['pollen'] = true;
            if (!empty($lab['classification']))    $has['classification'] = true;
        }

        return $has;
    }

    /**
     * Extrait un sous-ensemble stable de l'analyse laboratoire pour la vue comparateur.
     *
     * @return array<string, mixed>|null
     */
    private function extractLabAnalysis(Product $product): ?array
    {
        if ($product->isHasLabAnalysis() !== true) return null;

        $data = $product->getLabAnalysisDecoded();
        if (!\is_array($data)) return null;

        $params = $data['parameters'] ?? [];
        $pollen = $data['pollenAnalysis'] ?? [];

        return [
            'reference'      => $data['reference']  ?? $data['reportNumber'] ?? $data['labNumber'] ?? null,
            'reportDate'     => $data['reportDate'] ?? null,
            'labName'        => $data['labName']    ?? null,
            'humidity'       => $this->extractParam($params['humidity']     ?? null),
            'conductivity'   => $this->extractParam($params['conductivity'] ?? null, includeClassification: true),
            'ph'             => $this->extractParam($params['ph']           ?? null),
            'mgo'            => $this->extractParam($params['mgo']          ?? null),
            'npa'            => $this->extractParam($params['npa']          ?? null),
            'dha'            => $this->extractParam($params['dha']          ?? null),
            'hmf'            => $this->extractParam($params['hmf']          ?? null),
            'pollenDominant' => $this->extractDominantPollen($pollen),
            'classification' => $params['conductivity']['classification'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $param
     * @return array{value: string|null, note: string|null, classification?: string|null}|null
     */
    private function extractParam(?array $param, bool $includeClassification = false): ?array
    {
        if ($param === null || ($param['requested'] ?? true) === false) return null;

        $valueText = $param['valueText'] ?? null;
        if ($valueText === null && isset($param['value']) && $param['value'] !== null) {
            $unit = $param['unit'] ?? '';
            $valueText = trim($param['value'] . ' ' . $unit);
        }

        $result = [
            'value' => $valueText,
            'note'  => $param['note'] ?? null,
        ];

        if ($includeClassification) {
            $result['classification'] = $param['classification'] ?? null;
        }

        return $result;
    }

    /**
     * Retourne le pollen dominant ou, à défaut, l'entrée majoritaire de pollenPercentages.
     *
     * @param array<string, mixed> $pollen
     */
    private function extractDominantPollen(array $pollen): ?string
    {
        $dominant = $pollen['dominant'] ?? null;
        if (\is_string($dominant) && $dominant !== '' && stripos($dominant, 'pas de pollen') === false) {
            return $dominant;
        }

        $percentages = $pollen['pollenPercentages'] ?? [];
        if (\is_array($percentages) && $percentages !== []) {
            $top = $percentages[0];
            $name  = $top['pollen'] ?? null;
            $value = $top['value'] ?? null;
            $unit  = $top['unit'] ?? '%';

            if (\is_string($name) && $value !== null) {
                return \sprintf('%s %s %s', ucfirst($name), $value, $unit);
            }
        }

        return $dominant ?: null;
    }
}
