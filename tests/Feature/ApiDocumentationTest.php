<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests do not see the api documentation menu item', function () {
    $this->get('/docs/about')
        ->assertOk()
        ->assertDontSee(route('docs.api'));
});

test('guests are redirected away from the api documentation', function () {
    $this->get('/docs/api')->assertRedirect('/login');
});

test('any authenticated user can see the api documentation and its menu item', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/docs/about')
        ->assertOk()
        ->assertSee(route('docs.api'));

    $this->actingAs($user)
        ->get('/docs/api')
        ->assertOk()
        ->assertSee('Rodnik.today Mobile API v1')
        ->assertSee('POST')
        ->assertSee('/auth/token')
        ->assertSee('/springs/{spring}')
        ->assertSee('/reports/{report}/photos')
        ->assertSee('/photos/{photo}')
        ->assertSee('Recommended save workflow')
        ->assertSee('Create the report before uploading photos')
        ->assertSee('Persist the returned report ID')
        ->assertSee('Upload photos sequentially in the desired display order')
        ->assertSee('Client state model')
        ->assertSee('Partial success and recovery')
        ->assertSee('do not retry it blindly')
        ->assertSee('Retry-After')
        ->assertSee('API client best practices')
        ->assertSee('noindex, nofollow', false);
});
