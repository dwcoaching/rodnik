<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * Registration and password reset have a single password field with a "show" toggle,
     * so repeating the password is asked for only where the form has that field.
     *
     * @return array
     */
    protected function passwordRules()
    {
        return ['required', 'string', new Password];
    }
}
