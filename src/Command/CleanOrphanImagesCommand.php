<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Supprime les images orphelines (produits et blogs) non référencées en base.
 *
 * Usage :
 *   php bin/console app:clean-orphan-images          → liste + supprime
 *   php bin/console app:clean-orphan-images --dry-run → liste uniquement
 */
#[AsCommand(
    name: 'app:clean-orphan-images',
    description: 'Supprime les images orphelines (produits, blogs) non référencées en base.',
)]
final class CleanOrphanImagesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Filesystem $fs,
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les fichiers orphelins sans les supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $base   = \rtrim($this->publicDir, '/') . '/assets/images';

        $total = 0;
        $total += $this->cleanDir(
            $io,
            $base . '/products',
            'SELECT filename FROM media WHERE product_id IS NOT NULL AND filename IS NOT NULL',
            'Produits',
            $dryRun,
        );
        $total += $this->cleanDir(
            $io,
            $base . '/blogs',
            'SELECT m.filename FROM media m INNER JOIN blog_media bm ON bm.media_id = m.id WHERE m.filename IS NOT NULL',
            'Blogs',
            $dryRun,
        );
        $total += $this->cleanDir(
            $io,
            $base . '/categories',
            'SELECT filename FROM media WHERE category_id IS NOT NULL AND filename IS NOT NULL',
            'Catégories',
            $dryRun,
        );

        if ($total === 0) {
            $io->success('Aucun fichier orphelin trouvé.');
        } elseif ($dryRun) {
            $io->note("Mode dry-run : {$total} fichier(s) orphelin(s) détecté(s). Relancez sans --dry-run pour supprimer.");
        } else {
            $io->success("{$total} fichier(s) orphelin(s) supprimé(s).");
        }

        return Command::SUCCESS;
    }

    private function cleanDir(SymfonyStyle $io, string $dir, string $sql, string $label, bool $dryRun): int
    {
        if (!\is_dir($dir)) {
            return 0;
        }

        $rows  = $this->connection->fetchAllAssociative($sql);
        $known = [];
        foreach ($rows as $row) {
            $known[\pathinfo($row['filename'], \PATHINFO_FILENAME)] = true;
        }

        $orphans = [];
        foreach (new \DirectoryIterator($dir) as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }

            $filename     = $file->getFilename();
            $base         = \pathinfo($filename, \PATHINFO_FILENAME);
            $originalBase = \preg_replace('/-(thumb|medium)$/', '', $base);

            if (!isset($known[$originalBase])) {
                $orphans[] = ['path' => $file->getPathname(), 'name' => $filename];
            }
        }

        if ($orphans === []) {
            return 0;
        }

        $count = \count($orphans);
        $io->section("{$label} : {$count} fichier(s) orphelin(s)");
        $io->listing(\array_column($orphans, 'name'));

        if (!$dryRun) {
            foreach ($orphans as $orphan) {
                $this->fs->remove($orphan['path']);
            }
        }

        return $count;
    }
}
