<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

use Cline\Ruler\Core\Context;
use Cline\Ruler\Core\Rule;
use Cline\Ruler\Core\RuleCompiler;
use Closure;
use Throwable;

/**
 * Predicate evaluator backed by the `cline/ruler` rule engine.
 *
 * This adapter keeps `FormulaEvaluator` independent from the concrete
 * predicate implementation. It compiles array rule definitions, projects the
 * formula scope into the flat context expected by Ruler, and converts
 * compilation or runtime failures into `FormulaEvaluationResult` failures
 * instead of leaking third-party exceptions across the package boundary.
 *
 * Scope flattening overlays alias variables after the root context so nested
 * collection aliases shadow equally named top-level values during predicate
 * evaluation, matching the main evaluator's variable-resolution rules.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class RulerPredicateEvaluator implements PredicateEvaluatorInterface
{
    /**
     * Accepts an optional custom executor for compiled rules.
     *
     * This seam primarily exists for tests and integration scenarios that need
     * to intercept compiled `Rule` instances before the default Ruler context
     * execution path runs.
     *
     * @param null|Closure(Rule, array{root: array<string, mixed>, vars: array<string, mixed>}): bool $ruleEvaluator
     */
    public function __construct(
        private ?Closure $ruleEvaluator = null,
    ) {}

    /**
     * Compile and evaluate a predicate rule within the current formula scope.
     *
     * Compilation failures are mapped to
     * `FormulaErrorCode::PredicateCompilationFailed`. Exceptions thrown during
     * rule execution are mapped to
     * `FormulaErrorCode::PredicateEvaluationFailed`. Successful evaluation
     * returns the boolean match result from the underlying rule engine.
     *
     * @param array<string, mixed>                                          $rule
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    public function evaluate(array $rule, array $scope): FormulaEvaluationResult
    {
        $compiled = RuleCompiler::compileFromArray($rule);

        if (!$compiled->isSuccess()) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::PredicateCompilationFailed,
                $compiled->getError()?->getMessage() ?? 'Predicate compilation failed.',
            );
        }

        try {
            $matched = $this->ruleEvaluator instanceof Closure
                ? ($this->ruleEvaluator)($compiled->getRule(), $scope)
                : $this->evaluateCompiledRule($compiled->getRule(), $scope);
        } catch (Throwable $throwable) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::PredicateEvaluationFailed,
                $throwable->getMessage(),
            );
        }

        return FormulaEvaluationResult::success($matched);
    }

    /**
     * Flatten the split formula scope into the single-level map Ruler expects.
     *
     * Root values are loaded first and alias variables are overlaid last so
     * nested aliases win on name collisions.
     *
     * @param  array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     * @return array<string, mixed>
     */
    private function flattenScope(array $scope): array
    {
        return [
            ...$scope['root'],
            ...$scope['vars'],
        ];
    }

    /**
     * Evaluate a precompiled rule through the default Ruler context API.
     *
     * This method isolates the production execution path from the optional
     * injected closure used for custom evaluation behavior.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateCompiledRule(Rule $compiledRule, array $scope): bool
    {
        return $compiledRule->evaluate(
            new Context($this->flattenScope($scope)),
        );
    }
}
