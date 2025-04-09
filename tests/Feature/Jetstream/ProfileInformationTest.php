<?php

use Faker\Factory;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;


test('current profile information is available', function () {
    $this->actingAs($user = User::factory()->create());

    $component = Livewire::test(UpdateProfileInformationForm::class);

    expect($component->state['name'])->toEqual($user->name);
    expect($component->state['email'])->toEqual($user->email);
});

test('profile information can be updated', function () {
    $this->actingAs($user = User::factory()->create());

    $faker = Factory::create();
    $newName = $faker->name();
    $newEmail = $faker->unique()->safeEmail();

    Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => $newName, 'email' => $newEmail])
            ->call('updateProfileInformation');

    expect($user->fresh()->name)->toEqual($newName);
    expect($user->fresh()->email)->toEqual($newEmail);
});
