<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

/**
 * Contract for predicate engines embedded inside formula evaluation.
 *
 * Formula expressions delegate the `predicate` operator to this interface so
 * the main evaluator stays independent from any concrete rule engine or DSL.
 * Implementations receive the evaluator's split scope, where `root` contains
 * the original evaluation context and `vars` contains alias bindings created
 * by higher-order operators such as `map`, `filter`, `sum`, `maxOf`, and
 * `minOf`.
 *
 * Implementations should normalize engine-specific failures into
 * `FormulaEvaluationResult` failures rather than throwing. That preserves the
 * package-wide convention that formula execution reports errors structurally.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface PredicateEvaluatorInterface
{
    /**
     * Evaluate a predicate rule against the current formula scope.
     *
     * Alias bindings in `vars` are expected to take precedence over equally
     * named top-level keys in `root`, matching the scope-shadowing semantics of
     * the main formula interpreter.
     *
     * @param array<string, mixed>                                          $rule
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    public function evaluate(array $rule, array $scope): FormulaEvaluationResult;
}
