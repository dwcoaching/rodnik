<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @return User
     */
    public function create(array $input)
    {
        $this->honeypotCheck($input);

        return $this->register($input);
    }

    /**
     * Validate and create a user without the web form's honeypot fields (used by the API).
     *
     * @return User
     */
    public function register(array $input)
    {
        Validator::make($input, $this->rules())->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }

    /**
     * Sign-up rules shared by the web form and the API.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
        ];
    }

    public function honeypotCheck(array $input): void
    {
        if (! array_key_exists('nickname', $input)
            || ! is_null($input['nickname'])
            || array_key_exists('country', $input)) {
            abort(422);
        }
    }
}
