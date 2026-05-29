<?php

declare(strict_types=1);

// Validation. `validate($input, $rules)` is a pure function: it takes the input
// and a rules table as arguments, touches no global state, and either returns
// clean data (only the declared keys, with `int` coerced) or throws a
// ValidationException carrying the per-field errors and the original input.
//
// All side effects of a failure live in validation_middleware (at the bottom),
// the framework-owned layer that catches the exception and turns it into a
// response — a 422 JSON body for API clients, or a redirect back to the form
// with the errors and old input flashed for the next request. Handlers just
// call validate() and use the clean data; they never try/catch it themselves.
//
// Rules are a flat dispatch table (one tiny predicate + message per rule), so
// `grep` for a rule name finds exactly what it does. Adding a rule is adding an
// entry — nothing hidden, no reflection, no rule classes.

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors field => human messages
     * @param array<string, mixed>        $input   the original input, for old()
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $input,
    ) {
        parent::__construct('The given data was invalid.');
    }
}

/**
 * Validate $input against $rules (field => 'rule|rule:arg|...').
 *
 * Returns the clean data — only the keys named in $rules, with the `int` rule
 * coercing its value to a real int. Throws ValidationException listing every
 * failure when anything is invalid.
 *
 * A field that is empty/absent and not `required` is treated as optional: it
 * passes through untouched and its remaining rules are skipped.
 *
 * @param array<string, mixed>  $input
 * @param array<string, string> $rules
 * @return array<string, mixed>
 */
function validate(array $input, array $rules): array
{
    $clean = [];
    $errors = [];

    foreach ($rules as $field => $ruleString) {
        $ruleList = explode('|', $ruleString);
        $value = $input[$field] ?? null;

        $isEmpty = $value === null || $value === '';
        $isRequired = in_array('required', $ruleList, true);

        if ($isEmpty) {
            if ($isRequired) {
                $errors[$field][] = validation_message('required', $field, '');
            } else {
                // Optional + empty: keep as-is, skip the remaining rules.
                $clean[$field] = $value;
            }

            continue;
        }

        foreach ($ruleList as $rule) {
            if ($rule === 'required') {
                continue;
            }

            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, '');

            if (!validation_passes($name, $value, $arg, $input, $field)) {
                $errors[$field][] = validation_message($name, $field, $arg);
            }
        }

        // Coerce after validating so the clean value matches the asserted type.
        $clean[$field] = in_array('int', $ruleList, true) && !isset($errors[$field])
            ? (int) $value
            : $value;
    }

    if ($errors !== []) {
        throw new ValidationException($errors, $input);
    }

    return $clean;
}

/**
 * Whether a single rule passes for $value. Pure; $input/$field are only needed
 * by relational rules (e.g. `confirmed` reads "{field}_confirmation").
 *
 * @param array<string, mixed> $input
 */
function validation_passes(string $rule, mixed $value, string $arg, array $input, string $field): bool
{
    return match ($rule) {
        'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false,
        'int' => filter_var((string) $value, FILTER_VALIDATE_INT) !== false,
        'max' => mb_strlen((string) $value) <= (int) $arg,
        'min' => mb_strlen((string) $value) >= (int) $arg,
        'in' => in_array((string) $value, explode(',', $arg), true),
        'confirmed' => isset($input[$field . '_confirmation'])
            && (string) $input[$field . '_confirmation'] === (string) $value,
        default => throw new InvalidArgumentException("Unknown validation rule: {$rule}"),
    };
}

/**
 * The default human-readable message for a failed rule. `$field` is rendered
 * as-is (snake_case stays); keep these short and form-friendly.
 */
function validation_message(string $rule, string $field, string $arg): string
{
    return match ($rule) {
        'required' => "The {$field} field is required.",
        'email' => "The {$field} must be a valid email address.",
        'int' => "The {$field} must be an integer.",
        'max' => "The {$field} may not be longer than {$arg} characters.",
        'min' => "The {$field} must be at least {$arg} characters.",
        'in' => "The selected {$field} is invalid.",
        'confirmed' => "The {$field} confirmation does not match.",
        default => "The {$field} is invalid.",
    };
}

/**
 * Middleware: turn a ValidationException thrown by a handler into a response.
 * API clients (Accept: application/json) get a 422 with the errors; everyone
 * else is redirected back to the same URL with the errors and old input flashed
 * for the next request. Must run inside session_middleware so flashing works.
 */
function validation_middleware(Request $request, callable $next): mixed
{
    try {
        return $next($request);
    } catch (ValidationException $e) {
        $accept = $request->headers['Accept'] ?? '';
        if (is_string($accept) && str_contains($accept, 'application/json')) {
            return json(['errors' => $e->errors], 422);
        }

        flash_errors($e->errors);
        flash_old($e->input);

        return redirect($request->path);
    }
}
