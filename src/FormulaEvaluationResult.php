<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

/**
 * Immutable transport object for formula evaluation outcomes.
 *
 * The evaluator reports both successful values and failures through this type
 * so recursive operator execution can short-circuit without relying on
 * exceptions. A successful result guarantees `errorCode` and `errorMessage`
 * are `null`. A failed result guarantees `value` is `null` and the error
 * fields describe why evaluation stopped.
 *
 * This invariant lets operators propagate child failures unchanged and gives
 * downstream callers a stable machine-readable error code plus a human-readable
 * diagnostic message.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaEvaluationResult
{
    /**
     * Create a normalized result instance.
     *
     * Construction is private so callers must use the named factories and keep
     * the success/failure invariants intact.
     */
    private function __construct(
        public bool $succeeded,
        public mixed $value,
        public ?FormulaErrorCode $errorCode,
        public ?string $errorMessage,
    ) {}

    /**
     * Create a successful result carrying the computed value.
     *
     * The value remains `mixed` because different operators resolve to numbers,
     * booleans, arrays, strings, or `null`.
     */
    public static function success(mixed $value): self
    {
        return new self(
            succeeded: true,
            value: $value,
            errorCode: null,
            errorMessage: null,
        );
    }

    /**
     * Create a failed result carrying the first terminal evaluation error.
     *
     * Failure results intentionally discard any value payload and instead
     * preserve the stable enum code plus a descriptive message.
     */
    public static function failure(FormulaErrorCode $errorCode, string $errorMessage): self
    {
        return new self(
            succeeded: false,
            value: null,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }
}
