<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

use stdClass;

use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_key_first;
use function array_map;
use function array_shift;
use function count;
use function explode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function sprintf;
use function ucfirst;

/**
 * Recursive interpreter for the formula expression language.
 *
 * The evaluator walks a JSON-like expression tree and resolves each node into
 * a `FormulaEvaluationResult`. It distinguishes literals from operator nodes,
 * resolves variable paths against the current scope, enforces operand shape and
 * runtime type rules, and delegates predicate execution to
 * `PredicateEvaluatorInterface`.
 *
 * Evaluation is fail-fast. The first invalid node, missing variable, or type
 * mismatch returns a failure result that bubbles back up unchanged. Nested
 * collection operators maintain lexical-style alias bindings in `vars`, while
 * the original caller context remains in `root`. Alias variables always shadow
 * equally named top-level root keys.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class FormulaEvaluator
{
    /**
     * @param PredicateEvaluatorInterface $predicateEvaluator Predicate engine used by the `predicate` operator
     */
    public function __construct(
        private PredicateEvaluatorInterface $predicateEvaluator = new RulerPredicateEvaluator(),
    ) {}

    /**
     * Evaluate a formula against the provided root context.
     *
     * Callers provide only top-level context data. Nested alias scopes are
     * introduced internally by higher-order operators and stored in the `vars`
     * portion of the recursive scope structure.
     *
     * @param array<string, mixed> $expression
     * @param array<string, mixed> $context
     */
    public function evaluate(array $expression, array $context): FormulaEvaluationResult
    {
        return $this->evaluateNode($expression, [
            'root' => $context,
            'vars' => [],
        ]);
    }

    /**
     * Evaluate a single node in the expression tree.
     *
     * Scalar values are treated as inline literals. Arrays may represent a
     * `{const: ...}` wrapper, a `{var: ...}` lookup, or exactly one operator
     * node. Any other array shape is rejected before operator dispatch.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateNode(mixed $node, array $scope): FormulaEvaluationResult
    {
        if ($this->isScalar($node)) {
            return FormulaEvaluationResult::success($node);
        }

        if (!is_array($node) || $node === []) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidExpression,
                'Formula node must be a non-empty array.',
            );
        }

        if (array_key_exists('const', $node)) {
            return FormulaEvaluationResult::success($node['const']);
        }

        if (array_key_exists('var', $node)) {
            /** @var array<string, mixed> $node */
            return $this->evaluateVariable($node, $scope);
        }

        if (count($node) !== 1) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidExpression,
                'Formula operator nodes must define exactly one operator.',
            );
        }

        $operator = (string) array_key_first($node);
        $operand = $node[$operator];

        return match ($operator) {
            'predicate' => $this->evaluatePredicate($operand, $scope),
            'count' => $this->evaluateCount($operand, $scope),
            'add' => $this->evaluateAdd($operand, $scope),
            'subtract' => $this->evaluateSubtract($operand, $scope),
            'multiply' => $this->evaluateMultiply($operand, $scope),
            'divide' => $this->evaluateDivide($operand, $scope),
            'max' => $this->evaluateMax($operand, $scope),
            'min' => $this->evaluateMin($operand, $scope),
            'coalesce' => $this->evaluateCoalesce($operand, $scope),
            'equals' => $this->evaluateEquals($operand, $scope),
            'notEquals' => $this->evaluateNotEquals($operand, $scope),
            'greaterThan' => $this->evaluateGreaterThan($operand, $scope),
            'greaterThanOrEqual' => $this->evaluateGreaterThanOrEqual($operand, $scope),
            'lessThan' => $this->evaluateLessThan($operand, $scope),
            'lessThanOrEqual' => $this->evaluateLessThanOrEqual($operand, $scope),
            'and' => $this->evaluateAnd($operand, $scope),
            'or' => $this->evaluateOr($operand, $scope),
            'not' => $this->evaluateNot($operand, $scope),
            'if' => $this->evaluateIf($operand, $scope),
            'sum' => $this->evaluateSum($operand, $scope),
            'map' => $this->evaluateMap($operand, $scope),
            'filter' => $this->evaluateFilter($operand, $scope),
            'maxOf' => $this->evaluateMaxOf($operand, $scope),
            'minOf' => $this->evaluateMinOf($operand, $scope),
            default => FormulaEvaluationResult::failure(
                FormulaErrorCode::UnsupportedOperator,
                sprintf('Unsupported operator [%s].', $operator),
            ),
        };
    }

    /**
     * Resolve a variable node against the current scope.
     *
     * Variable paths are dot-delimited. Resolution first checks alias bindings
     * in `vars` using the first path segment, then falls back to the immutable
     * root context. When the path is missing and the node defines `default`,
     * that default is returned as a successful value. Otherwise the lookup
     * fails with `FormulaErrorCode::MissingVariable`.
     *
     * @param array<string, mixed>                                          $node
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateVariable(array $node, array $scope): FormulaEvaluationResult
    {
        $path = $node['var'] ?? null;

        if (!is_string($path) || $path === '') {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidPath,
                'Variable path must be a non-empty string.',
            );
        }

        $resolved = $this->resolveVariable($path, $scope);

        if (!$resolved instanceof stdClass) {
            return FormulaEvaluationResult::success($resolved);
        }

        if (array_key_exists('default', $node)) {
            return FormulaEvaluationResult::success($node['default']);
        }

        return FormulaEvaluationResult::failure(
            FormulaErrorCode::MissingVariable,
            sprintf('Variable path [%s] could not be resolved.', $path),
        );
    }

    /**
     * Delegate the `predicate` operator to the configured predicate evaluator.
     *
     * The operand must contain a `rule` array. Any compilation or runtime
     * issues inside the backing predicate engine are normalized by the delegate
     * into a failed `FormulaEvaluationResult`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluatePredicate(mixed $operand, array $scope): FormulaEvaluationResult
    {
        if (!is_array($operand) || !is_array($operand['rule'] ?? null)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Predicate operator requires a ruler rule definition.',
            );
        }

        /** @var array<string, mixed> $rule */
        $rule = $operand['rule'];

        return $this->predicateEvaluator->evaluate($rule, $scope);
    }

    /**
     * Evaluate `count`, requiring the resolved operand to be an array.
     *
     * The operator counts only arrays produced by formula evaluation. Other
     * scalar values are not coerced into iterable form.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateCount(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $resolved = $this->evaluateNode($operand, $scope);

        if (!$resolved->succeeded) {
            return $resolved;
        }

        if (!is_array($resolved->value)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::NonIterableInput,
                'Count operator expects an array operand.',
            );
        }

        return FormulaEvaluationResult::success(count($resolved->value));
    }

    /**
     * Reduce a list of numeric operands using addition.
     *
     * An empty list resolves to the additive identity `0.0`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateAdd(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->reduceNumericOperands(
            $operand,
            $scope,
            static fn (float $carry, float $value): float => $carry + $value,
            0.0,
        );
    }

    /**
     * Reduce a list of numeric operands using multiplication.
     *
     * An empty list resolves to the multiplicative identity `1.0`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateMultiply(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->reduceNumericOperands(
            $operand,
            $scope,
            static fn (float $carry, float $value): float => $carry * $value,
            1.0,
        );
    }

    /**
     * Evaluate ordered binary subtraction.
     *
     * The operator requires exactly two numeric operands and subtracts the
     * second value from the first.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateSubtract(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $operands = $this->evaluateNumericOperandList($operand, $scope, expectedCount: 2);

        if ($operands instanceof FormulaEvaluationResult) {
            return $operands;
        }

        return FormulaEvaluationResult::success($operands[0] - $operands[1]);
    }

    /**
     * Evaluate ordered binary division with explicit zero-divisor protection.
     *
     * The operator requires exactly two numeric operands and fails with
     * `FormulaErrorCode::DivisionByZero` when the divisor resolves to `0.0`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateDivide(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $operands = $this->evaluateNumericOperandList($operand, $scope, expectedCount: 2);

        if ($operands instanceof FormulaEvaluationResult) {
            return $operands;
        }

        if ($operands[1] === 0.0) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::DivisionByZero,
                'Divide operator does not allow division by zero.',
            );
        }

        return FormulaEvaluationResult::success($operands[0] / $operands[1]);
    }

    /**
     * Return the largest value from a non-empty numeric operand list.
     *
     * Empty input is rejected because there is no neutral maximum value that
     * preserves the operator's meaning.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateMax(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $operands = $this->evaluateNumericOperandList($operand, $scope);

        if ($operands instanceof FormulaEvaluationResult) {
            return $operands;
        }

        if ($operands === []) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Max operator expects at least one numeric operand.',
            );
        }

        return FormulaEvaluationResult::success(max($operands));
    }

    /**
     * Return the smallest value from a non-empty numeric operand list.
     *
     * Empty input is rejected for the same reason as `max`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateMin(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $operands = $this->evaluateNumericOperandList($operand, $scope);

        if ($operands instanceof FormulaEvaluationResult) {
            return $operands;
        }

        if ($operands === []) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Min operator expects at least one numeric operand.',
            );
        }

        return FormulaEvaluationResult::success(min($operands));
    }

    /**
     * Evaluate operands left-to-right and return the first non-null success.
     *
     * If every branch either fails or resolves to `null`, the last failure is
     * returned. Only when all branches succeed and resolve to `null` does the
     * operator succeed with `null`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateCoalesce(mixed $operand, array $scope): FormulaEvaluationResult
    {
        if (!is_array($operand) || !array_is_list($operand)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Coalesce operator expects a list of operands.',
            );
        }

        $lastFailure = null;

        foreach ($operand as $entry) {
            $result = $this->evaluateNode($entry, $scope);

            if ($result->succeeded && $result->value !== null) {
                return $result;
            }

            $lastFailure = $result;
        }

        return $lastFailure instanceof FormulaEvaluationResult
            ? $lastFailure
            : FormulaEvaluationResult::success(null);
    }

    /**
     * Compare two evaluated operands using strict identity semantics.
     *
     * No loose PHP coercion is performed.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateEquals(mixed $operand, array $scope): FormulaEvaluationResult
    {
        [$left, $right] = $this->evaluatePair($operand, $scope);

        if (!$left->succeeded) {
            return $left;
        }

        if (!$right->succeeded) {
            return $right;
        }

        return FormulaEvaluationResult::success($left->value === $right->value);
    }

    /**
     * Compare two evaluated operands using strict non-identity semantics.
     *
     * This is the inverse of `equals` and also avoids loose PHP coercion.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateNotEquals(mixed $operand, array $scope): FormulaEvaluationResult
    {
        [$left, $right] = $this->evaluatePair($operand, $scope);

        if (!$left->succeeded) {
            return $left;
        }

        if (!$right->succeeded) {
            return $right;
        }

        return FormulaEvaluationResult::success($left->value !== $right->value);
    }

    /**
     * Compare a numeric pair using the greater-than operator.
     *
     * Both operands must resolve to numeric values before comparison occurs.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateGreaterThan(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->compareNumericPair($operand, $scope, static fn (float $left, float $right): bool => $left > $right);
    }

    /**
     * Compare a numeric pair using the greater-than-or-equal operator.
     *
     * Both operands must resolve to numeric values before comparison occurs.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateGreaterThanOrEqual(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->compareNumericPair($operand, $scope, static fn (float $left, float $right): bool => $left >= $right);
    }

    /**
     * Compare a numeric pair using the less-than operator.
     *
     * Both operands must resolve to numeric values before comparison occurs.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateLessThan(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->compareNumericPair($operand, $scope, static fn (float $left, float $right): bool => $left < $right);
    }

    /**
     * Compare a numeric pair using the less-than-or-equal operator.
     *
     * Both operands must resolve to numeric values before comparison occurs.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateLessThanOrEqual(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->compareNumericPair($operand, $scope, static fn (float $left, float $right): bool => $left <= $right);
    }

    /**
     * Reduce a list of boolean operands using logical conjunction.
     *
     * Operands must already resolve to booleans; the evaluator does not apply
     * PHP truthiness coercion here.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateAnd(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->reduceBooleanOperands(
            $operand,
            $scope,
            static fn (bool $carry, bool $value): bool => $carry && $value,
            true,
        );
    }

    /**
     * Reduce a list of boolean operands using logical disjunction.
     *
     * Operands must already resolve to booleans; the evaluator does not apply
     * PHP truthiness coercion here.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateOr(mixed $operand, array $scope): FormulaEvaluationResult
    {
        return $this->reduceBooleanOperands(
            $operand,
            $scope,
            static fn (bool $carry, bool $value): bool => $carry || $value,
            false,
        );
    }

    /**
     * Invert a single boolean operand.
     *
     * Non-boolean values are rejected instead of being coerced via PHP's `!`
     * semantics.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateNot(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $resolved = $this->evaluateNode($operand, $scope);

        if (!$resolved->succeeded) {
            return $resolved;
        }

        if (!is_bool($resolved->value)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Not operator expects a boolean operand.',
            );
        }

        return FormulaEvaluationResult::success(!$resolved->value);
    }

    /**
     * Evaluate a ternary-style conditional in `[condition, then, else]` form.
     *
     * Only the selected branch is evaluated after the condition succeeds and
     * resolves to a boolean, so the operator is lazy with respect to the
     * then/else branches.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateIf(mixed $operand, array $scope): FormulaEvaluationResult
    {
        if (!is_array($operand) || !array_is_list($operand) || count($operand) !== 3) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'If operator expects [condition, then, else].',
            );
        }

        $condition = $this->evaluateNode($operand[0], $scope);

        if (!$condition->succeeded) {
            return $condition;
        }

        if (!is_bool($condition->value)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'If condition must resolve to a boolean value.',
            );
        }

        return $this->evaluateNode($condition->value ? $operand[1] : $operand[2], $scope);
    }

    /**
     * Project a collection and add the resulting numeric values.
     *
     * The `in` expression is evaluated once per collection item with the
     * declared alias bound into `vars`. Every projected value must be numeric.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateSum(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $mapped = $this->evaluateCollectionProjection($operand, $scope, 'in');

        if ($mapped instanceof FormulaEvaluationResult) {
            return $mapped;
        }

        $sum = 0.0;

        foreach ($mapped as $value) {
            if (!is_numeric($value)) {
                return FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    'Sum operator requires numeric projected values.',
                );
            }

            $sum += (float) $value;
        }

        return FormulaEvaluationResult::success($sum);
    }

    /**
     * Project each item in a collection and return the resulting list.
     *
     * Alias bindings are scoped per item and do not leak back into the caller.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateMap(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $mapped = $this->evaluateCollectionProjection($operand, $scope, 'in');

        return $mapped instanceof FormulaEvaluationResult
            ? $mapped
            : FormulaEvaluationResult::success($mapped);
    }

    /**
     * Filter a collection by evaluating a boolean predicate for each item.
     *
     * The `where` expression runs with the declared alias bound in `vars`.
     * Items are retained only when the predicate succeeds and resolves to
     * `true`.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateFilter(mixed $operand, array $scope): FormulaEvaluationResult
    {
        [$collection, $alias, $where] = $this->resolveCollectionDefinition($operand, $scope, 'where');

        if ($collection instanceof FormulaEvaluationResult) {
            return $collection;
        }

        $filtered = [];

        foreach ($collection as $item) {
            $nestedScope = [
                'root' => $scope['root'],
                'vars' => [...$scope['vars'], $alias => $item],
            ];

            $result = $this->evaluateNode($where, $nestedScope);

            if (!$result->succeeded) {
                return $result;
            }

            if (!is_bool($result->value)) {
                return FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    'Filter predicate must resolve to a boolean value.',
                );
            }

            if (!$result->value) {
                continue;
            }

            $filtered[] = $item;
        }

        return FormulaEvaluationResult::success($filtered);
    }

    /**
     * Project a collection and return the maximum projected numeric value.
     *
     * Every projected value must be numeric and the projection must not be
     * empty. Mixed numeric and non-numeric output is rejected.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateMaxOf(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $mapped = $this->evaluateCollectionProjection($operand, $scope, 'in');

        if ($mapped instanceof FormulaEvaluationResult) {
            return $mapped;
        }

        $numeric = array_map(
            static fn (mixed $value): float => (float) $value,
            array_filter($mapped, is_numeric(...)),
        );

        if ($numeric === [] || count($numeric) !== count($mapped)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'MaxOf operator requires numeric projected values.',
            );
        }

        return FormulaEvaluationResult::success(max($numeric));
    }

    /**
     * Project a collection and return the minimum projected numeric value.
     *
     * Every projected value must be numeric and the projection must not be
     * empty. Mixed numeric and non-numeric output is rejected.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function evaluateMinOf(mixed $operand, array $scope): FormulaEvaluationResult
    {
        $mapped = $this->evaluateCollectionProjection($operand, $scope, 'in');

        if ($mapped instanceof FormulaEvaluationResult) {
            return $mapped;
        }

        $numeric = array_map(
            static fn (mixed $value): float => (float) $value,
            array_filter($mapped, is_numeric(...)),
        );

        if ($numeric === [] || count($numeric) !== count($mapped)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'MinOf operator requires numeric projected values.',
            );
        }

        return FormulaEvaluationResult::success(min($numeric));
    }

    /**
     * Evaluate a higher-order collection projection.
     *
     * This shared helper powers `map`, `sum`, `maxOf`, and `minOf`. It resolves
     * the collection definition, binds each item to the declared alias in a
     * nested scope, evaluates the requested projection expression, and returns
     * the raw projected values.
     *
     * @param  array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     * @return array<int, mixed>|FormulaEvaluationResult
     */
    private function evaluateCollectionProjection(mixed $operand, array $scope, string $projectionKey): array|FormulaEvaluationResult
    {
        [$collection, $alias, $projection] = $this->resolveCollectionDefinition($operand, $scope, $projectionKey);

        if ($collection instanceof FormulaEvaluationResult) {
            return $collection;
        }

        $results = [];

        foreach ($collection as $item) {
            $nestedScope = [
                'root' => $scope['root'],
                'vars' => [...$scope['vars'], $alias => $item],
            ];

            $result = $this->evaluateNode($projection, $nestedScope);

            if (!$result->succeeded) {
                return $result;
            }

            $results[] = $result->value;
        }

        return $results;
    }

    /**
     * Resolve the common `from`/`as`/projection contract for collection operators.
     *
     * The source expression must evaluate to an array. The returned tuple uses
     * the first slot as either the resolved collection or an already prepared
     * failure result so callers can short-circuit without additional wrapping.
     *
     * @param  array{root: array<string, mixed>, vars: array<string, mixed>}                   $scope
     * @return array{0: array<int|string, mixed>|FormulaEvaluationResult, 1: string, 2: mixed}
     */
    private function resolveCollectionDefinition(mixed $operand, array $scope, string $projectionKey): array
    {
        if (
            !is_array($operand)
            || !is_array($operand['from'] ?? null)
            || !is_string($operand['as'] ?? null)
            || !is_array($operand[$projectionKey] ?? null)
        ) {
            return [
                FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    sprintf('%s operator requires from/as/%s members.', ucfirst($projectionKey), $projectionKey),
                ),
                '',
                [],
            ];
        }

        $resolvedCollection = $this->evaluateNode($operand['from'], $scope);

        if (!$resolvedCollection->succeeded) {
            return [$resolvedCollection, '', []];
        }

        if (!is_array($resolvedCollection->value)) {
            return [
                FormulaEvaluationResult::failure(
                    FormulaErrorCode::NonIterableInput,
                    sprintf('%s operator expects an iterable source.', ucfirst($projectionKey)),
                ),
                '',
                [],
            ];
        }

        return [$resolvedCollection->value, $operand['as'], $operand[$projectionKey]];
    }

    /**
     * Resolve a list of operands and coerce each successful value to float.
     *
     * Child evaluation failures are returned unchanged. Successful non-numeric
     * values are converted into `FormulaErrorCode::InvalidOperand`.
     *
     * @param  array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     * @return array<int, float>|FormulaEvaluationResult
     */
    private function evaluateNumericOperandList(
        mixed $operand,
        array $scope,
        ?int $expectedCount = null,
    ): array|FormulaEvaluationResult {
        if (!is_array($operand) || !array_is_list($operand)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Operator expects a list operand.',
            );
        }

        if ($expectedCount !== null && count($operand) !== $expectedCount) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                sprintf('Operator expects exactly %d numeric operands.', $expectedCount),
            );
        }

        $values = [];

        foreach ($operand as $entry) {
            $result = $this->evaluateNode($entry, $scope);

            if (!$result->succeeded) {
                return $result;
            }

            if (!is_numeric($result->value)) {
                return FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    'Operator expects numeric operands.',
                );
            }

            $values[] = (float) $result->value;
        }

        return $values;
    }

    /**
     * Fold a numeric operand list with the provided reducer and seed.
     *
     * This helper backs variadic arithmetic operators whose semantics are fully
     * defined by numeric reduction.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function reduceNumericOperands(
        mixed $operand,
        array $scope,
        callable $reducer,
        float $seed,
    ): FormulaEvaluationResult {
        $operands = $this->evaluateNumericOperandList($operand, $scope);

        if ($operands instanceof FormulaEvaluationResult) {
            return $operands;
        }

        $value = $seed;

        foreach ($operands as $entry) {
            $value = $reducer($value, $entry);
        }

        return FormulaEvaluationResult::success($value);
    }

    /**
     * Fold a boolean operand list with the provided reducer and seed.
     *
     * Boolean operators validate explicit booleans instead of accepting PHP's
     * broader notion of truthy and falsy values.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function reduceBooleanOperands(
        mixed $operand,
        array $scope,
        callable $reducer,
        bool $seed,
    ): FormulaEvaluationResult {
        if (!is_array($operand) || !array_is_list($operand)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Operator expects a list operand.',
            );
        }

        $value = $seed;

        foreach ($operand as $entry) {
            $result = $this->evaluateNode($entry, $scope);

            if (!$result->succeeded) {
                return $result;
            }

            if (!is_bool($result->value)) {
                return FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    'Boolean operator expects boolean operands.',
                );
            }

            $value = $reducer($value, $result->value);
        }

        return FormulaEvaluationResult::success($value);
    }

    /**
     * Evaluate and return the left/right pair required by binary operators.
     *
     * Invalid operand structure is represented as two identical failures so the
     * caller can keep a uniform "return left failure, else right failure" flow.
     *
     * @param  array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     * @return array{0: FormulaEvaluationResult, 1: FormulaEvaluationResult}
     */
    private function evaluatePair(mixed $operand, array $scope): array
    {
        if (!is_array($operand) || !array_is_list($operand) || count($operand) !== 2) {
            return [
                FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    'Comparison operator expects exactly two operands.',
                ),
                FormulaEvaluationResult::failure(
                    FormulaErrorCode::InvalidOperand,
                    'Comparison operator expects exactly two operands.',
                ),
            ];
        }

        return [
            $this->evaluateNode($operand[0], $scope),
            $this->evaluateNode($operand[1], $scope),
        ];
    }

    /**
     * Compare a binary pair after numeric validation and float coercion.
     *
     * This helper backs the ordered comparison operators.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function compareNumericPair(mixed $operand, array $scope, callable $comparator): FormulaEvaluationResult
    {
        [$left, $right] = $this->evaluatePair($operand, $scope);

        if (!$left->succeeded) {
            return $left;
        }

        if (!$right->succeeded) {
            return $right;
        }

        if (!is_numeric($left->value) || !is_numeric($right->value)) {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::InvalidOperand,
                'Comparison operator expects numeric operands.',
            );
        }

        return FormulaEvaluationResult::success(
            $comparator((float) $left->value, (float) $right->value),
        );
    }

    /**
     * Determine whether a value is treated as an inline literal node.
     *
     * Arrays are excluded because they may represent operator nodes or
     * structured data that must be wrapped in `{const: ...}` to stay literal.
     */
    private function isScalar(mixed $value): bool
    {
        return $value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value);
    }

    /**
     * Resolve a dot-delimited variable path using formula scope precedence.
     *
     * The first segment is checked against `vars` to support alias shadowing in
     * nested collection operators. When no alias matches, the full path is
     * resolved from the immutable root context.
     *
     * @param array{root: array<string, mixed>, vars: array<string, mixed>} $scope
     */
    private function resolveVariable(string $path, array $scope): mixed
    {
        $segments = explode('.', $path);
        $head = array_shift($segments);

        if (array_key_exists($head, $scope['vars'])) {
            return $this->resolvePath($scope['vars'][$head], $segments);
        }

        return $this->resolvePath($scope['root'], explode('.', $path));
    }

    /**
     * Traverse an array path and return a sentinel when any segment is missing.
     *
     * A fresh `stdClass` instance is used as the missing-value sentinel so the
     * evaluator can distinguish "path not found" from a legitimate `null`
     * value stored at the resolved location.
     *
     * @param list<string> $segments
     */
    private function resolvePath(mixed $value, array $segments): mixed
    {
        $current = $value;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return new stdClass();
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
