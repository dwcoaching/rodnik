<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can not register if the nickname field is not present', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $response->assertStatus(422);
});

test('user can not register if the nickname field is not empty', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'nickname' => 'John',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $response->assertStatus(422);
});

test('user can not register if the country field is present', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'country' => 'Russia',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $response->assertStatus(422);
});

test('user can register', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'nickname' => '',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $response->assertRedirect();
});


