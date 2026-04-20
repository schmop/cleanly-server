<?php

declare(strict_types=1);

namespace App\Task\Exception;

class ReminderComputationException extends \RuntimeException
{
    /**
     * Run a closure and translate date arithmetic failures into a domain-specific
     * ReminderComputationException. Used to wrap reminder/recurring computations
     * that may fail with DateMalformed*, DateInvalidOperation, or DivisionByZeroError.
     *
     * @template T
     * @param \Closure(): T $fn
     * @return T
     * @throws ReminderComputationException
     */
    public static function wrap(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (
            \DateMalformedStringException
            | \DateMalformedIntervalStringException
            | \DateInvalidOperationException
            | \DivisionByZeroError $e
        ) {
            throw new self($e->getMessage(), 0, $e);
        }
    }
}
