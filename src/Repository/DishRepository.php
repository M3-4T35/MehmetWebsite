<?php

namespace App\Repository;

use App\Entity\Dish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dish>
 */
class DishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dish::class);
    }

    /**
     * Dishes in display order: explicit position first, newest first on ties.
     *
     * @return Dish[]
     */
    public function findForGallery(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'createdAt' => 'DESC']);
    }
}
