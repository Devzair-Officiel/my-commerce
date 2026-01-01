<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\Product;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service responsable de la gestion physique des fichiers médias.
 *
 * - storeProductFile() :
 *   Génère un nom de fichier unique et stocke l'image sur le disque.
 *
 * - removeFile() :
 *   Supprime le fichier du disque, même si l'entité Media
 *   n'a plus de relation (cas orphanRemoval Doctrine).
 *
 * Objectif : isoler toute la logique filesystem hors des contrôleurs
 * et des entités (SRP / Clean Architecture).
 */
final class MediaFileManager
{
    public function __construct(
        private Filesystem $fs,
        private string $uploadDirProducts,
        private string $uploadDirCategories,
        private string $uploadDirSliders,
        private string $uploadDirSettings,
        private SluggerInterface $slugger,
    ) {}

    public function removeFile(Media $media): void
    {
        $filename = (string) $media->getFilename();
        if ($filename === '') {
            return;
        }

        $paths = [];

        // cas normal : on sait si c'est un média produit, setting, catégorie
        if ($media->getProduct() !== null) {
            $paths[] = rtrim($this->uploadDirProducts, '/') . '/' . $filename;
        }

        if ($media->getCategory() !== null) {
            $paths[] = rtrim($this->uploadDirCategories, '/') . '/' . $filename;
        }

        if ($media->getSliders() !== null) {
            $paths[] = rtrim($this->uploadDirSliders, '/') . '/' . $filename;
        }

        if ($media->getSetting() !== null) {
            $paths[] = rtrim($this->uploadDirSettings, '/') . '/' . $filename;
        }

        // cas orphanRemoval : relation null au moment du postRemove
        if ($paths === []) {
            $paths[] = rtrim($this->uploadDirProducts, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirCategories, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirSliders, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirSettings, '/') . '/' . $filename;
        }

        foreach (array_unique($paths) as $path) {
            if ($this->fs->exists($path)) {
                $this->fs->remove($path);
            }
        }
    }

    public function storeProductFile(UploadedFile $file, Product $product): string
    {
        $baseName = $product->getSlug() ?: $product->getTitle() ?: 'product';
        $slug = $this->slugger->slug($baseName)->lower();

        $extension = $file->guessExtension()
            ?: $file->getClientOriginalExtension()
            ?: 'bin';

        $filename = sprintf('%s-%s.%s', $slug, bin2hex(random_bytes(8)), $extension);

        $file->move(rtrim($this->uploadDirProducts, '/'), $filename);

        return $filename;
    }
    
}
