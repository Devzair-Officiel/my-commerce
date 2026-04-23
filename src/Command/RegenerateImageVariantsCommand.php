<?php

namespace App\Command;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande de maintenance qui régénère les variantes d'images (-thumb, -medium, -large)
 * pour tous les médias uploadés (produits, blogs, catégories, sliders, etc.).
 *
 * Tailles :
 *   -thumb  : 650px  — cards produits (324px) et vignettes (82px) à 2× retina
 *   -medium : 960px  — image principale produit (660px) + cards blog (435px)
 *   -large  : 1400px — hero article blog (1040px) à ~1.35× retina
 */
#[AsCommand(
    name: 'app:regenerate-image-variants',
    description: 'Régénère les variantes -thumb, -medium et -large pour toutes les images uploadées.',
)]
final class RegenerateImageVariantsCommand extends Command
{
    private const THUMB_MAX  = 650;
    private const MEDIUM_MAX = 960;
    private const LARGE_MAX  = 1400;

    private const DIRS = [
        'products',
        'blogs',
        'categories',
        'sliders',
        'setting',
        'payment_methods_logos',
    ];

    public function __construct(private string $publicDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait sans écrire de fichiers');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Régénère même si les variantes existent déjà');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $force  = (bool) $input->getOption('force');

        if (!\extension_loaded('gd')) {
            $io->error('L\'extension GD n\'est pas disponible.');
            return Command::FAILURE;
        }

        $manager   = new ImageManager(new Driver());
        $imagesDir = rtrim($this->publicDir, '/') . '/assets/images';

        $generated = 0;
        $skipped   = 0;
        $errors    = 0;

        foreach (self::DIRS as $subDir) {
            $dir = $imagesDir . '/' . $subDir;
            if (!is_dir($dir)) {
                continue;
            }

            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }

                $filename = $file->getFilename();
                $info     = pathinfo($filename);
                $base     = $info['filename'] ?? '';
                $ext      = $info['extension'] ?? '';

                // Ignorer les variantes déjà générées
                if (
                    str_ends_with($base, '-thumb')  ||
                    str_ends_with($base, '-medium') ||
                    str_ends_with($base, '-large')
                ) {
                    continue;
                }

                // Ignorer les fichiers non-image
                if (!\in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)) {
                    continue;
                }

                $sourcePath  = $file->getPathname();
                $thumbPath   = $dir . '/' . $base . '-thumb.webp';
                $mediumPath  = $dir . '/' . $base . '-medium.webp';
                $largePath   = $dir . '/' . $base . '-large.webp';

                $needsThumb  = $force || !file_exists($thumbPath);
                $needsMedium = $force || !file_exists($mediumPath);
                $needsLarge  = $force || !file_exists($largePath);

                if (!$needsThumb && !$needsMedium && !$needsLarge) {
                    $io->writeln(
                        sprintf('<comment>Ignoré (variantes déjà présentes)</comment> %s/%s', $subDir, $filename),
                        OutputInterface::VERBOSITY_VERBOSE
                    );
                    ++$skipped;
                    continue;
                }

                $io->writeln(sprintf('<info>Traitement</info> %s/%s', $subDir, $filename));

                if ($dryRun) {
                    ++$generated;
                    continue;
                }

                try {
                    if ($needsThumb) {
                        $manager->decode($sourcePath)
                            ->cover(self::THUMB_MAX, self::THUMB_MAX)
                            ->save($thumbPath);
                        $io->writeln(sprintf('  → thumb  : %s', basename($thumbPath)), OutputInterface::VERBOSITY_VERBOSE);
                    }

                    if ($needsMedium) {
                        $manager->decode($sourcePath)
                            ->scaleDown(self::MEDIUM_MAX, self::MEDIUM_MAX)
                            ->save($mediumPath);
                        $io->writeln(sprintf('  → medium : %s', basename($mediumPath)), OutputInterface::VERBOSITY_VERBOSE);
                    }

                    if ($needsLarge) {
                        $manager->decode($sourcePath)
                            ->scaleDown(self::LARGE_MAX, self::LARGE_MAX)
                            ->save($largePath);
                        $io->writeln(sprintf('  → large  : %s', basename($largePath)), OutputInterface::VERBOSITY_VERBOSE);
                    }

                    ++$generated;
                } catch (\Throwable $e) {
                    $io->warning(sprintf('Erreur sur %s/%s : %s', $subDir, $filename, $e->getMessage()));
                    ++$errors;
                }
            }
        }

        $io->success(sprintf(
            '%d image(s) traitée(s), %d ignorée(s) (variantes existantes), %d erreur(s).%s',
            $generated,
            $skipped,
            $errors,
            $dryRun ? ' [dry-run, aucun fichier écrit]' : '',
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
