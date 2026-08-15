<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Laravel\Fortify\TwoFactorAuthenticatable;

uses(RefreshDatabase::class);

test('two factor authentication is unavailable', function () {
    expect(Features::canManageTwoFactorAuthentication())->toBeFalse()
        ->and(class_uses_recursive(User::class))->not->toContain(TwoFactorAuthenticatable::class)
        ->and(Schema::hasColumn('users', 'two_factor_secret'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'two_factor_recovery_codes'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'two_factor_confirmed_at'))->toBeFalse();
});

test('two factor authentication routes are unavailable', function (string $routeName) {
    expect(Route::has($routeName))->toBeFalse();
})->with([
    'two-factor.login',
    'two-factor.login.store',
    'two-factor.enable',
    'two-factor.confirm',
    'two-factor.disable',
    'two-factor.qr-code',
    'two-factor.secret-key',
    'two-factor.recovery-codes',
    'two-factor.regenerate-recovery-codes',
]);
