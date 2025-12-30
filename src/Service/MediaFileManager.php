<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\Product;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaFileManager 
{
    public function __construct(
        private Filesystem $fs,
        private string $uploadDirProducts,
        private string $uploadDirCategories,
        private SluggerInterface $slugger,
    )
    {}

    public function removeFile(Media $media): void
    {
        $path = $this->resolvePath($media);
        if($path === null) {
            return;
        }

        if($this->fs->exists($path)) {
            $this->fs->remove($path);
        }
    }

    public function resolvePath(Media $media): ?string
    {
        $filename = $media->getFilename();

        if($media->getProduct() !== null) {
            return $this->uploadDirProducts . '/' . $filename;
        }

        if ($media->getCategory() !== null) {
            return $this->uploadDirCategories . '/' . $filename;
        }

        return null;

    }

    public function storeProductFile(UploadedFile $file, Product $product): string
    {
        $baseName = $product->getSlug() ?: $product->getTitle() ?: 'product';
        $slug = $this->slugger->slug($baseName)->lower();

        $extension = $file->guessExtension()
            ?? $file->getClientOriginalExtension()
            ?? 'bin';

        // ⚠️ IMPORTANT : produit = multi-images → éviter les collisions
        $filename = sprintf(
            '%s-%s.%s',
            $slug,
            uniqid(),
            $extension
        );

        $file->move($this->uploadDirProducts, $filename);

        return $filename;
    }
}