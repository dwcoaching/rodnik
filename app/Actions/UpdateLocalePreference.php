<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class UpdateLocalePreference
{
    public function __invoke(?User $user, string $locale): void
    {
        $this->authorize($user);
        $locale = $this->validate($locale);
        $this->execute($user, $locale);
    }

    public function authorize(?User $user): void
    {
        if ($user) {
            Gate::forUser($user)->allowIf(fn (User $authenticatedUser): bool => $authenticatedUser->is($user));
        }
    }

    public function validate(string $locale): string
    {
        return Validator::validate(
            ['locale' => $locale],
            ['locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('localization.supported')))]],
        )['locale'];
    }

    public function execute(?User $user, string $locale): void
    {
        if (! $user || $user->locale === $locale) {
            return;
        }

        $user->forceFill(['locale' => $locale])->save();
    }
}
