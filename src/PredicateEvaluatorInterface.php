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
 */
interface PredicateEvaluatorInterface
{
    /**
     * @param array<string, mixed>                                          $rule
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    public function evaluate(array $rule, array $scope): FormulaEvaluationResult;
}
