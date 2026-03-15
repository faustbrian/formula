<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Formula;

/**
 * Stable error taxonomy for invalid formulas and runtime evaluation failures.
 *
 * The package reports errors through `FormulaEvaluationResult` rather than
 * exceptions, and this enum provides the durable identifiers callers can match
 * on. Codes cover malformed expression trees, invalid operand types or shapes,
 * scope/path lookup failures, and delegated predicate engine failures.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum FormulaErrorCode: string
{
    /** The expression tree shape is malformed or violates core formula rules. */
    case InvalidExpression = 'invalid_expression';

    /** The operator name is not recognized by the evaluator. */
    case UnsupportedOperator = 'unsupported_operator';

    /** A variable path is syntactically invalid before lookup begins. */
    case InvalidPath = 'invalid_path';

    /** A division operator attempted to use zero as the divisor. */
    case DivisionByZero = 'division_by_zero';

    /** An operator requiring an iterable source received a non-array value. */
    case NonIterableInput = 'non_iterable_input';

    /** Variable lookup failed and the expression did not provide a default. */
    case MissingVariable = 'missing_variable';

    /** The operand shape or resolved runtime type is invalid for the operator. */
    case InvalidOperand = 'invalid_operand';

    /** The delegated predicate engine could not compile the supplied rule. */
    case PredicateCompilationFailed = 'predicate_compilation_failed';

    /** The delegated predicate engine failed while executing a compiled rule. */
    case PredicateEvaluationFailed = 'predicate_evaluation_failed';
}
