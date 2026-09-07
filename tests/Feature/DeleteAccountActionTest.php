<?php

declare(strict_types=1);

use App\Actions\DeleteAccountAction;
use App\Library\Export\ExportLock;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Models\TrackPolygon;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('photos');
});

test('account deletion preserves public contributions and removes their account attribution', function () {
    $user = User::factory()->create(['profile_photo_path' => 'profile-photos/owner.jpg']);
    $other = User::factory()->create();
    $report = Report::factory()->for($user)->create(['updated_at' => now()->subDays(3)]);
    $hidden = Report::factory()->for($user)->create([
        'hidden_at' => now(),
        'hidden_by_author_id' => $user->id,
    ]);
    $otherReport = Report::factory()->for($other)->create([
        'hidden_at' => now(),
        'hidden_by_moderator_id' => $user->id,
    ]);
    $photo = Photo::factory()->for($report)->create([
        'original_filename' => 'Owner Real Name.jpg',
        'updated_at' => now()->subDays(3),
    ]);
    $revision = SpringRevision::forceCreate([
        'user_id' => $user->id,
        'spring_id' => $report->spring_id,
        'revision_type' => 'user',
        'new_name' => 'Public spring name',
    ]);
    $track = TrackPolygon::factory()->for($user)->create();
    $otherTrack = TrackPolygon::factory()->for($other)->create();
    $token = $user->createToken('deleted device');
    $otherToken = $other->createToken('retained device');
    Password::createToken($user);
    Password::createToken($other);

    DB::table(config('session.table'))->insert([
        ['id' => 'deleted-session', 'user_id' => $user->id, 'payload' => 'session', 'last_activity' => time()],
        ['id' => 'retained-session', 'user_id' => $other->id, 'payload' => 'session', 'last_activity' => time()],
    ]);

    Storage::disk('public')->put($user->profile_photo_path, 'avatar');
    Storage::disk('public')->put('exports/rodnik-from-before.json', 'contains author name');
    Storage::disk('public')->put('exports/users/rodnik-user-'.$user->id.'-from-before.json', 'contains author name');
    Storage::disk('public')->put('exports/users/rodnik-user-'.$other->id.'-from-before.json', 'may contain old spring authors');
    Storage::disk('public')->put('other-file.txt', 'unrelated');
    Storage::disk('photos')->put($photo->filename, 'public report photo');

    $this->actingAs($user);

    app(DeleteAccountAction::class)($user);

    $this->assertModelMissing($user);
    $this->assertModelExists($other);
    expect($report->fresh()->user_id)->toBeNull()
        ->and($report->fresh()->comment)->toBe($report->comment)
        ->and($report->fresh()->updated_at->equalTo($report->updated_at))->toBeTrue()
        ->and($hidden->fresh()->hidden_at)->not->toBeNull()
        ->and($hidden->fresh()->user_id)->toBeNull()
        ->and($hidden->fresh()->hidden_by_author_id)->toBeNull()
        ->and($otherReport->fresh()->user_id)->toBe($other->id)
        ->and($otherReport->fresh()->hidden_by_moderator_id)->toBeNull()
        ->and($revision->fresh()->user_id)->toBeNull()
        ->and($revision->fresh()->new_name)->toBe('Public spring name')
        ->and($photo->fresh()->original_filename)->toBe($photo->filename)
        ->and($photo->fresh()->updated_at->equalTo($photo->updated_at))->toBeTrue();

    $this->assertModelMissing($track);
    $this->assertModelExists($otherTrack);
    $this->assertModelMissing($token->accessToken);
    $this->assertModelExists($otherToken->accessToken);
    $this->assertDatabaseMissing(config('auth.passwords.users.table'), ['email' => $user->email]);
    $this->assertDatabaseHas(config('auth.passwords.users.table'), ['email' => $other->email]);
    $this->assertDatabaseMissing(config('session.table'), ['user_id' => $user->id]);
    $this->assertDatabaseHas(config('session.table'), ['user_id' => $other->id]);
    Storage::disk('public')->assertMissing($user->profile_photo_path);
    Storage::disk('public')->assertDirectoryEmpty('exports');
    Storage::disk('public')->assertExists('other-file.txt');
    Storage::disk('photos')->assertExists($photo->filename);
});

test('removing account attribution preserves revision order and the latest spring details', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $spring = Spring::factory()->create([
        'name' => 'OSM name',
        'osm_name' => 'OSM name',
        'type' => 'Spring',
        'osm_type' => 'Spring',
        'latitude' => 50,
        'osm_latitude' => 50,
        'longitude' => 30,
        'osm_longitude' => 30,
    ]);
    $older = SpringRevision::forceCreate([
        'user_id' => $user->id,
        'spring_id' => $spring->id,
        'revision_type' => 'user',
        'new_name' => 'Older name',
        'new_type' => 'Water well',
        'new_latitude' => 51,
        'new_longitude' => 31,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);
    $newer = SpringRevision::forceCreate([
        'user_id' => $other->id,
        'spring_id' => $spring->id,
        'revision_type' => 'user',
        'new_name' => 'Newer name',
        'new_type' => 'Water tap',
        'new_latitude' => 52,
        'new_longitude' => 32,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    $this->actingAs($user);

    app(DeleteAccountAction::class)($user);

    $spring->refresh();
    expect($older->fresh()->user_id)->toBeNull()
        ->and($older->fresh()->updated_at->equalTo($older->updated_at))->toBeTrue()
        ->and($spring->getRodnikName())->toBe($newer->new_name)
        ->and($spring->getRodnikType())->toBe($newer->new_type)
        ->and((float) $spring->getRodnikLatitude())->toBe((float) $newer->new_latitude)
        ->and((float) $spring->getRodnikLongitude())->toBe((float) $newer->new_longitude);
});

test('another authenticated user cannot delete an account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($other);

    expect(fn () => app(DeleteAccountAction::class)($user))->toThrow(AuthorizationException::class);

    $this->assertModelExists($user);
    $this->assertModelExists($other);
});

test('a guest cannot delete an account', function () {
    $user = User::factory()->create();

    expect(fn () => app(DeleteAccountAction::class)($user))->toThrow(AuthorizationException::class);

    $this->assertModelExists($user);
});

test('account deletion validates that the account exists', function () {
    $user = User::factory()->make();
    $this->actingAs($user);

    expect(fn () => app(DeleteAccountAction::class)($user))->toThrow(ValidationException::class);
});

test('account deletion rolls back when stored exports cannot be removed', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->create();
    $token = $user->createToken('device');
    $this->actingAs($user);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('allFiles')->once()->with('exports')->andReturn(['exports/old.json']);
    $disk->shouldReceive('delete')->once()->with(['exports/old.json'])->andReturn(false);
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    expect(fn () => app(DeleteAccountAction::class)($user))->toThrow(RuntimeException::class);

    $this->assertModelExists($user);
    $this->assertModelExists($token->accessToken);
    expect($report->fresh()->user_id)->toBe($user->id);
});

test('account deletion does not begin while contribution exports hold the lock', function () {
    $user = User::factory()->create();
    $report = Report::factory()->for($user)->create();
    $token = $user->createToken('device');
    $this->actingAs($user);

    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')
        ->once()
        ->with(ExportLock::WAIT_SECONDS, Mockery::type(Closure::class))
        ->andThrow(new LockTimeoutException);
    Cache::shouldReceive('lock')->once()->with(ExportLock::NAME, ExportLock::SECONDS)->andReturn($lock);

    expect(fn () => app(DeleteAccountAction::class)($user))->toThrow(LockTimeoutException::class);

    $this->assertModelExists($user);
    $this->assertModelExists($token->accessToken);
    expect($report->fresh()->user_id)->toBe($user->id);
});
