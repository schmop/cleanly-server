<?php

declare(strict_types=1);

namespace App\Persistence;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

class PersistenceException extends \RuntimeException
{
    /**
     * Run a closure and translate Doctrine persistence failures into a
     * domain-specific PersistenceException.
     *
     * @template T
     * @param \Closure(): T $fn
     * @return T
     * @throws PersistenceException
     */
    public static function wrap(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (UniqueConstraintViolationException | ORMException $e) {
            throw new self($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws PersistenceException
     */
    public static function persistAndFlush(EntityManagerInterface $em, object $entity): void
    {
        self::wrap(function () use ($em, $entity): void {
            $em->persist($entity);
            $em->flush();
        });
    }

    /**
     * @throws PersistenceException
     */
    public static function removeAndFlush(EntityManagerInterface $em, object $entity): void
    {
        self::wrap(function () use ($em, $entity): void {
            $em->remove($entity);
            $em->flush();
        });
    }

    /**
     * @throws PersistenceException
     */
    public static function flush(EntityManagerInterface $em): void
    {
        self::wrap(static fn() => $em->flush());
    }
}
