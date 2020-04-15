<?php

declare(strict_types=1);

/*
 * @author  Moritz Vondano
 * @license MIT
 */

namespace Mvo\ContaoSurvey\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Mvo\ContaoSurvey\Entity\Question;

class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * @return Question[]
     */
    public function findBefore(Question $question): array
    {
        $qb = $this->createQueryBuilder('sq')
            ->where('sq.sorting < :sorting')
            ->andWhere('sq.timestamp > 0')
            ->setParameter('sorting', $question->getSorting())
            ->orderBy('sq.sorting');

        return $qb->getQuery()->execute();
    }
}
