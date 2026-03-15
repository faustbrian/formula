<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaErrorCode;
use Cline\Formula\RulerPredicateEvaluator;
use Cline\Ruler\Core\Rule;

it('evaluates successful ruler predicates directly', function (): void {
    $evaluator = new RulerPredicateEvaluator();

    $result = $evaluator->evaluate([
        'field' => 'shipment.route.destination',
        'operator' => 'sameAs',
        'value' => 'FI',
    ], [
        'root' => [
            'shipment' => [
                'route' => [
                    'destination' => 'FI',
                ],
            ],
        ],
        'vars' => [],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBeTrue();
});

it('returns structured failures when ruler predicate evaluation throws', function (): void {
    $evaluator = new RulerPredicateEvaluator(
        static fn (Rule $compiledRule, array $scope): bool => throw new RuntimeException('rule runtime exploded'),
    );

    $result = $evaluator->evaluate([
        'field' => 'shipment.route.destination',
        'operator' => 'sameAs',
        'value' => 'FI',
    ], [
        'root' => [
            'shipment' => [
                'route' => [
                    'destination' => 'FI',
                ],
            ],
        ],
        'vars' => [],
    ]);

    expect($result->succeeded)->toBeFalse()
        ->and($result->errorCode)->toBe(FormulaErrorCode::PredicateEvaluationFailed)
        ->and($result->errorMessage)->toBe('rule runtime exploded');
});
