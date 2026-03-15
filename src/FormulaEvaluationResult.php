<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

/**
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaEvaluationResult
{
    private function __construct(
        public bool $succeeded,
        public mixed $value,
        public ?FormulaErrorCode $errorCode,
        public ?string $errorMessage,
    ) {}

    public static function success(mixed $value): self
    {
        return new self(
            succeeded: true,
            value: $value,
            errorCode: null,
            errorMessage: null,
        );
    }

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
