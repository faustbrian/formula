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
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class RulerPredicateEvaluator implements PredicateEvaluatorInterface
{
    /**
     * @param null|Closure(Rule, array{root: array<string, mixed>, vars: array<string, mixed>}): bool $ruleEvaluator
     */
    public function __construct(
        private ?Closure $ruleEvaluator = null,
    ) {}

    /**
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
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateCompiledRule(Rule $compiledRule, array $scope): bool
    {
        return $compiledRule->evaluate(
            new Context($this->flattenScope($scope)),
        );
    }
}
