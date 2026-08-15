<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('a user can exchange credentials for a sanctum token', function () {
    $user = User::factory()->create([
        'email' => 'mobile@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'correct-password',
        'device_name' => 'Test phone',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

    [$tokenId, $plainTextToken] = explode('|', $response->json('data.token'), 2);

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $tokenId,
        'tokenable_id' => $user->id,
        'name' => 'Test phone',
        'token' => hash('sha256', $plainTextToken),
        'expires_at' => null,
    ]);
});

test('invalid credentials return the same validation error', function (array $credentials) {
    User::factory()->create([
        'email' => 'known@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->postJson('/api/v1/auth/token', $credentials + ['device_name' => 'Test phone'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
})->with([
    'unknown email' => [['email' => 'unknown@example.com', 'password' => 'correct-password']],
    'incorrect password' => [['email' => 'known@example.com', 'password' => 'wrong-password']],
]);

test('token login is rate limited by email and ip', function () {
    User::factory()->create([
        'email' => 'limited@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/token', [
            'email' => 'limited@example.com',
            'password' => 'wrong-password',
            'device_name' => 'Test phone',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => 'limited@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Test phone',
    ])->assertTooManyRequests();
});

test('api authentication failures are json even without an accept header', function () {
    $this->get('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('the current user endpoint returns an explicit resource', function () {
    $user = User::factory()->create(['cached_rating' => 7]);
    $token = $user->createToken('Test phone')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.rating', 7)
        ->assertJsonMissingPath('data.password');
});

test('logout revokes only the current token', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('Current phone');
    $otherToken = $user->createToken('Other phone');

    $this->withToken($currentToken->plainTextToken)
        ->deleteJson('/api/v1/auth/token')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

    Auth::forgetGuards();
    $this->withToken($currentToken->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    Auth::forgetGuards();
    $this->withToken($otherToken->plainTextToken)->getJson('/api/v1/me')->assertOk();
});
