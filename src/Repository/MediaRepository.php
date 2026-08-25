<?php

namespace App\Repository;

use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Returns the lowest-position media of every project, indexed by project id.
     *
     * Executes a single query (avoids N+1 lookups when listing projects).
     *
     * @return array<int, Media>
     */
    public function findFirstPerProject(): array
    {
        $medias = $this->createQueryBuilder('m')
            ->addSelect('p')
            ->innerJoin('m.project', 'p')
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();

        $previews = [];
        foreach ($medias as $media) {
            $projectId = $media->getProject()->getId();
            if (!isset($previews[$projectId])) {
                $previews[$projectId] = $media;
            }
        }

        return $previews;
    }
}
