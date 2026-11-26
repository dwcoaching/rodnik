<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function registrationPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'New Walker',
        'email' => 'walker@example.com',
        'password' => 'correct-horse-battery',
        'device_name' => 'Test phone',
    ], $overrides);
}

test('a visitor can register without the web form honeypot fields and gets a token', function () {
    $response = $this->postJson('/api/v1/auth/register', registrationPayload(['locale' => 'ru']));

    $response->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.name', 'New Walker')
        ->assertJsonPath('data.user.email', 'walker@example.com')
        ->assertJsonPath('data.user.locale', 'ru');

    $user = User::query()->sole();
    expect(Hash::check('correct-horse-battery', $user->password))->toBeTrue();

    $this->withToken($response->json('data.token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('registration reports every invalid field at once using the web sign-up rules', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'taken@example.com',
        'password' => 'short',
        'locale' => 'de',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name', 'locale']);

    expect(User::query()->count())->toBe(1);
});

test('only three accounts can be registered per hour from one address, but typos do not count', function () {

    $this->postJson('/api/v1/auth/register', registrationPayload(['email' => 'not-an-email']))->assertUnprocessable();

    foreach (range(1, 3) as $index) {
        $this->postJson('/api/v1/auth/register', registrationPayload(['email' => "walker{$index}@example.com"]))
            ->assertCreated();
    }

    $this->postJson('/api/v1/auth/register', registrationPayload(['email' => 'walker4@example.com']))
        ->assertTooManyRequests();

    expect(User::query()->count())->toBe(3);
});
