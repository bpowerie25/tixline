<?php

namespace App\Rules;

use App\Services\WorkflowEngine;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that all matches_regex rules in a workflow conditions
 * array contain syntactically valid regex patterns.
 */
class ValidWorkflowRegex implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $this->validateRules($value, $fail);
    }

    protected function validateRules(array $conditions, Closure $fail): void
    {
        $rules = $conditions['rules'] ?? [];

        foreach ($rules as $rule) {
            // Nested group — recurse
            if (isset($rule['match'])) {
                $this->validateRules($rule, $fail);

                continue;
            }

            if (($rule['operator'] ?? '') === 'matches_regex') {
                $pattern = $rule['value'] ?? '';
                if (! WorkflowEngine::isValidRegex($pattern)) {
                    $field = $rule['field'] ?? 'unknown';
                    $fail("The regex pattern for field \"{$field}\" is invalid: \"{$pattern}\"");
                }
            }
        }
    }
}
