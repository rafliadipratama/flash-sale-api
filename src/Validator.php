<?php

namespace App;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        if (str_starts_with($rule, 'required')) {
            if ($value === null || $value === '') {
                $this->errors[$field] = "Field '{$field}' is required";
            }
        } elseif (str_starts_with($rule, 'numeric')) {
            if ($value !== null && !is_numeric($value)) {
                $this->errors[$field] = "Field '{$field}' must be numeric";
            }
        } elseif (str_starts_with($rule, 'positive')) {
            if ($value !== null && is_numeric($value) && $value <= 0) {
                $this->errors[$field] = "Field '{$field}' must be greater than 0";
            }
        } elseif (str_starts_with($rule, 'non_negative')) {
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
