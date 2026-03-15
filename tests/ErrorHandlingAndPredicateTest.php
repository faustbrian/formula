<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Formula\FormulaErrorCode;
use Cline\Formula\FormulaEvaluationResult;
use Cline\Formula\FormulaEvaluator;
use Cline\Formula\PredicateEvaluatorInterface;

it('evaluates embedded predicates through ruler', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'if' => [
            [
                'predicate' => [
                    'rule' => [
                        'field' => 'shipment.route.destination',
                        'operator' => 'sameAs',
                        'value' => 'FI',
                    ],
                ],
            ],
            ['const' => 'domestic'],
            ['const' => 'foreign'],
        ],
    ], [
        'shipment' => [
            'route' => [
                'destination' => 'FI',
            ],
        ],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBe('domestic');
});

it('returns structured failures for invalid input', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'divide' => [
            ['var' => 'a'],
            0,
        ],
    ], [
        'a' => 10,
    ]);

    expect($result->succeeded)->toBeFalse()
        ->and($result->errorCode)->toBe(FormulaErrorCode::DivisionByZero)
        ->and($result->errorMessage)->not->toBeNull();
});

it('fails with a structured error when a required variable is missing', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'add' => [
            ['var' => 'shipment.weight'],
            5,
        ],
    ], [
        'shipment' => [],
    ]);

    expect($result->succeeded)->toBeFalse()
        ->and($result->errorCode)->toBe(FormulaErrorCode::MissingVariable);
});

it('fails with a structured error when predicate compilation fails', function (): void {
    $evaluator = new FormulaEvaluator();

    $result = $evaluator->evaluate([
        'predicate' => [
            'rule' => [
                'field' => 'shipment.route.destination',
                'value' => 'FI',
            ],
        ],
    ], [
        'shipment' => [
            'route' => [
                'destination' => 'FI',
            ],
        ],
    ]);

    expect($result->succeeded)->toBeFalse()
        ->and($result->errorCode)->toBe(FormulaErrorCode::PredicateCompilationFailed);
});

it('delegates predicate nodes to the configured predicate evaluator', function (): void {
    $predicateEvaluator = new class() implements PredicateEvaluatorInterface
    {
        public function evaluate(array $rule, array $scope): FormulaEvaluationResult
        {
            expect($rule)->toBe([
                'field' => 'shipment.route.destination',
                'operator' => 'sameAs',
                'value' => 'FI',
            ]);
            expect($scope['root']['shipment']['route']['destination'])->toBe('FI');

            return FormulaEvaluationResult::success(true);
        }
    };

    $evaluator = new FormulaEvaluator($predicateEvaluator);

    $result = $evaluator->evaluate([
        'predicate' => [
            'rule' => [
                'field' => 'shipment.route.destination',
                'operator' => 'sameAs',
                'value' => 'FI',
            ],
        ],
    ], [
        'shipment' => [
            'route' => [
                'destination' => 'FI',
            ],
        ],
    ]);

    expect($result->succeeded)->toBeTrue()
        ->and($result->value)->toBeTrue();
});

it('surfaces predicate evaluation failures from the configured predicate engine', function (): void {
    $predicateEvaluator = new class() implements PredicateEvaluatorInterface
    {
        public function evaluate(array $rule, array $scope): FormulaEvaluationResult
        {
            return FormulaEvaluationResult::failure(
                FormulaErrorCode::PredicateEvaluationFailed,
                'predicate runtime failed',
            );
        }
    };

    $evaluator = new FormulaEvaluator($predicateEvaluator);

    $result = $evaluator->evaluate([
        'predicate' => [
            'rule' => [
                'field' => 'shipment.route.destination',
                'operator' => 'sameAs',
                'value' => 'FI',
            ],
        ],
    ], [
        'shipment' => [
            'route' => [
                'destination' => 'FI',
            ],
        ],
    ]);

    expect($result->succeeded)->toBeFalse()
        ->and($result->errorCode)->toBe(FormulaErrorCode::PredicateEvaluationFailed)
        ->and($result->errorMessage)->toBe('predicate runtime failed');
});
