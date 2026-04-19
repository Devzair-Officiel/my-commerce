<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /**
     * Setting "layout-safe" (scalaires uniquement) + logo filename/alt si dispo.
     *
     * @return array<string, mixed>|null
     */
    public function findOneForLayout(): ?array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.logoMedia', 'lm')
            ->select([
                's.id AS id',
                's.website_name AS website_name',
                's.description AS description',
                's.currency AS currency',
                's.taxe_rate AS taxe_rate',
                's.street AS street',
                's.city AS city',
                's.code_postal AS code_postal',
                's.state AS state',
                's.phone AS phone',
                's.email AS email',
                's.facebookLink AS facebookLink',
                's.instaLink AS instaLink',
                's.youtubeLink AS youtubeLink',
                's.copyright AS copyright',
                's.copyrightUrl AS copyrightUrl',
                'lm.filename AS logo_filename',
                'lm.alt AS logo_alt',
                's.faviconFilename AS favicon_filename',
                's.heroVideoFilename AS hero_video_filename',
                's.heroPosterFilename AS hero_poster_filename',
                's.heroTitle AS heroTitle',
                's.heroButtonText AS heroButtonText',
                's.heroButtonUrl AS heroButtonUrl',
            ])
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1);

        /** @var array<string, mixed>|null $row */
        $row = $qb->getQuery()->getOneOrNullResult();

        return $row ?: null;
    }
}
