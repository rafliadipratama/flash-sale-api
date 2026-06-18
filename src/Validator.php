<?php

namespace App;

class Validator
{
    private array $errors = [];

    /**
     * Validate data against a set of rules.
     * Collects all errors before returning, so client gets complete picture
     * instead of failing on first error.
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            // Apply each rule to this field
            // Rules are applied independently so a field can fail multiple rules
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        // Return true if no errors found
        return empty($this->errors);
    }

    /**
     * Apply a single validation rule to a field value.
     * Each rule checks one aspect (required, numeric, min length, etc).
     * If validation fails, error is added to $this->errors array.
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // 'required' rule: field must be present and not empty
        if (str_starts_with($rule, 'required')) {
            if ($value === null || $value === '') {
                $this->errors[$field] = "Field '{$field}' is required";
            }
        } elseif (str_starts_with($rule, 'numeric')) {
            if ($value !== null && !is_numeric($value)) {
                $this->errors[$field] = "Field '{$field}' must be numeric";
            }
        } elseif (str_starts_with($rule, 'positive')) {
            // 'positive' rule: strictly greater than 0 (used for prices, quantities)
            if ($value !== null && is_numeric($value) && $value <= 0) {
                $this->errors[$field] = "Field '{$field}' must be greater than 0";
            }
        } elseif (str_starts_with($rule, 'non_negative')) {
            // 'non_negative' rule: 0 or greater (used for inventory)
            // Allows 0 inventory but not negative
            if ($value !== null && is_numeric($value) && $value < 0) {
                $this->errors[$field] = "Field '{$field}' cannot be negative";
            }
        } elseif (str_starts_with($rule, 'integer')) {
            if ($value !== null && !is_int($value) && !ctype_digit((string)$value)) {
                $this->errors[$field] = "Field '{$field}' must be an integer";
            }
        } elseif (str_starts_with($rule, 'min:')) {
            $min = (int)substr($rule, 4);
            if ($value !== null && strlen((string)$value) < $min) {
                $this->errors[$field] = "Field '{$field}' must be at least {$min} characters";
            }
        } elseif (str_starts_with($rule, 'max:')) {
            $max = (int)substr($rule, 4);
            if ($value !== null && strlen((string)$value) > $max) {
                $this->errors[$field] = "Field '{$field}' must not exceed {$max} characters";
            }
        } elseif (str_starts_with($rule, 'string')) {
            if ($value !== null && !is_string($value)) {
                $this->errors[$field] = "Field '{$field}' must be a string";
            }
        } elseif (str_starts_with($rule, 'array')) {
            if ($value !== null && !is_array($value)) {
                $this->errors[$field] = "Field '{$field}' must be an array";
            }
        } elseif (str_starts_with($rule, 'min_items:')) {
            $min = (int)substr($rule, 10);
            if ($value !== null && is_array($value) && count($value) < $min) {
                $this->errors[$field] = "Field '{$field}' must have at least {$min} items";
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return reset($this->errors) ?: null;
    }
}
