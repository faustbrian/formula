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
enum FormulaErrorCode: string
{
    case InvalidExpression = 'invalid_expression';
    case UnsupportedOperator = 'unsupported_operator';
    case InvalidPath = 'invalid_path';
    case DivisionByZero = 'division_by_zero';
    case NonIterableInput = 'non_iterable_input';
    case MissingVariable = 'missing_variable';
    case InvalidOperand = 'invalid_operand';
    case PredicateCompilationFailed = 'predicate_compilation_failed';
    case PredicateEvaluationFailed = 'predicate_evaluation_failed';
}
