<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoDuplicateItem implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $ids = array_filter(array_column($value, 'id')); // ignore nulls
        if (count($ids) !== count(array_unique($ids))) {
            $fail('Duplicate item IDs are not allowed.');
        }
    }
}