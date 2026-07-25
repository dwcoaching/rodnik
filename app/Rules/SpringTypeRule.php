<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Spring;
use Illuminate\Contracts\Validation\Rule;

final class SpringTypeRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return in_array($value, Spring::TYPES);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('ui.validation.select_water_source_type');
    }
}
