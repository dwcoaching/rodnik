<?php

namespace Tests\Feature\Jetstream;

use Faker\Factory;
use Tests\TestCase;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;

class ProfileInformationTest extends TestCase
{
    public function test_current_profile_information_is_available()
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated()
    {
        $this->actingAs($user = User::factory()->create());

        $faker = Factory::create();
        $newName = $faker->name();
        $newEmail = $faker->unique()->safeEmail();

        Livewire::test(UpdateProfileInformationForm::class)
                ->set('state', ['name' => $newName, 'email' => $newEmail])
                ->call('updateProfileInformation');

        $this->assertEquals($newName, $user->fresh()->name);
        $this->assertEquals($newEmail, $user->fresh()->email);
    }
}
