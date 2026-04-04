<?php

namespace App\Service;

use App\Entity\Blog;
use App\Entity\Media;
use App\Entity\Product;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service responsable de la gestion physique des fichiers médias.
 *
 * storeProductFile() génère l'image originale ET deux variantes redimensionnées :
 *   - *-thumb.{ext}  → 300×300 max (liste produits, miniatures)
 *   - *-medium.{ext} → 800×800 max (page produit)
 *
 * removeFile() supprime les trois variantes.
 */
final class MediaFileManager
{
    private const THUMB_MAX  = 600;
    private const MEDIUM_MAX = 800;

    public function __construct(
        private Filesystem $fs,
        private string $uploadDirProducts,
        private string $uploadDirCategories,
        private string $uploadDirSliders,
        private string $uploadDirSettings,
        private string $uploadDirBlogs,
        private string $uploadDirPaymentMethod,
        private SluggerInterface $slugger,
    ) {}

    public function removeFile(Media $media): void
    {
        $filename = (string) $media->getFilename();
        if ($filename === '') {
            return;
        }

        $paths = [];

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

        if (!$media->getBlogs()->isEmpty()) {
            $paths[] = rtrim($this->uploadDirBlogs, '/') . '/' . $filename;
        }

        if ($media->getPaymentMethod() !== null) {
            $paths[] = rtrim($this->uploadDirPaymentMethod, '/') . '/' . $filename;
        }

        if ($paths === []) {
            $paths[] = rtrim($this->uploadDirProducts, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirCategories, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirSliders, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirSettings, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirBlogs, '/') . '/' . $filename;
            $paths[] = rtrim($this->uploadDirPaymentMethod, '/') . '/' . $filename;
        }

        foreach (array_unique($paths) as $path) {
            $this->removeWithVariants($path);
        }
    }

    public function storeProductFile(UploadedFile $file, Product $product): string
    {
        $baseName = $product->getSlug() ?: $product->getTitle() ?: 'product';
        $slug = $this->slugger->slug($baseName)->lower();

        $extension = $file->guessExtension()
            ?: $file->getClientOriginalExtension()
            ?: 'bin';

        $filename = \sprintf('%s-%s.%s', $slug, \bin2hex(\random_bytes(8)), $extension);
        $dir = \rtrim($this->uploadDirProducts, '/');

        $file->move($dir, $filename);

        $this->generateVariants($dir . '/' . $filename, $dir, $filename);

        return $filename;
    }

    public function storeBlogFile(UploadedFile $file, Blog $blog): string
    {
        $baseName = $blog->getSlug() ?: $blog->getTitle() ?: 'blog';
        $slug = $this->slugger->slug($baseName)->lower();

        $extension = $file->guessExtension()
            ?: $file->getClientOriginalExtension()
            ?: 'bin';

        $filename = \sprintf('%s-%s.%s', $slug, \bin2hex(\random_bytes(8)), $extension);
        $dir = \rtrim($this->uploadDirBlogs, '/');

        $file->move($dir, $filename);

        $this->generateVariants($dir . '/' . $filename, $dir, $filename);

        return $filename;
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Génère les variantes redimensionnées d'une image.
     * Les variantes sont nommées : basename-thumb.ext et basename-medium.ext
     */
    private function generateVariants(string $sourcePath, string $dir, string $filename): void
    {
        if (!\extension_loaded('gd')) {
            return; // GD non disponible : on laisse l'original tel quel
        }

        $info = \pathinfo($filename);
        $base = $info['filename'];
        $ext  = $info['extension'] ?? 'jpg';

        $manager = new ImageManager(new Driver());

        try {
            $image = $manager->decode($sourcePath);

            // Thumbnail : contenu recadré en carré 300×300
            $manager->decode($sourcePath)
                ->cover(self::THUMB_MAX, self::THUMB_MAX)
                ->save($dir . '/' . $base . '-thumb.' . $ext);

            // Medium : redimensionné dans la boîte 800×800, ratio conservé
            $image->scaleDown(self::MEDIUM_MAX, self::MEDIUM_MAX)
                ->save($dir . '/' . $base . '-medium.' . $ext);
        } catch (\Throwable) {
            // Si le fichier n'est pas une image (PDF, etc.) on ignore silencieusement
        }
    }

    /**
     * Supprime un fichier et ses variantes (-thumb, -medium).
     */
    private function removeWithVariants(string $path): void
    {
        if (!$this->fs->exists($path)) {
            return;
        }

        $this->fs->remove($path);

        $info   = \pathinfo($path);
        $dir    = $info['dirname'];
        $base   = $info['filename'];
        $ext    = $info['extension'] ?? '';

        foreach (['-thumb', '-medium'] as $suffix) {
            $variant = $dir . '/' . $base . $suffix . ($ext !== '' ? '.' . $ext : '');
            if ($this->fs->exists($variant)) {
                $this->fs->remove($variant);
            }
        }
    }
}
