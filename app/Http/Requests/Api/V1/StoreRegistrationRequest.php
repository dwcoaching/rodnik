<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reuses the web sign-up rules so both stay identical, and reports every invalid field at once.
 */
final class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...app(CreateNewUser::class)->rules(),
            'device_name' => ['required', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(config('localization.supported')))],
        ];
    }
}
