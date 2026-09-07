<?php

declare(strict_types=1);

use App\Library\Export\ExportLock;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use League\Flysystem\UnableToDeleteFile;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('user accounts can be deleted', function () {
    if (! Features::hasAccountDeletionFeatures()) {
        return $this->markTestSkipped('Account deletion is not enabled.');
    }

    $this->actingAs($user = User::factory()->create());
    $token = $user->createToken('phone')->plainTextToken;

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser');

    expect($user->fresh())->toBeNull();
    $this->assertGuest();

    $this->getJson(route('api.v1.me.show'), ['Authorization' => 'Bearer '.$token])
        ->assertUnauthorized();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email']);
});

test('correct password must be provided before account can be deleted', function () {
    if (! Features::hasAccountDeletionFeatures()) {
        return $this->markTestSkipped('Account deletion is not enabled.');
    }

    $this->actingAs($user = User::factory()->create());

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('account deletion displays a retry message when an export is busy', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Exceptions::fake();

    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')
        ->once()
        ->with(ExportLock::WAIT_SECONDS, Mockery::type(Closure::class))
        ->andThrow(new LockTimeoutException);
    Cache::shouldReceive('lock')->with(ExportLock::NAME, ExportLock::SECONDS)->andReturn($lock);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['accountDeletion'])
        ->assertSee(__('privacy.deletion.retry'));

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
    Exceptions::assertReported(LockTimeoutException::class);
});

test('account deletion displays a retry message when stored files cannot be removed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Exceptions::fake();

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('allFiles')->once()->with('exports')->andReturn(['exports/old.json']);
    $disk->shouldReceive('delete')->once()->with(['exports/old.json'])
        ->andThrow(UnableToDeleteFile::atLocation('exports/old.json'));
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['accountDeletion'])
        ->assertSee(__('privacy.deletion.retry'));

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
    Exceptions::assertReported(UnableToDeleteFile::class);
});
