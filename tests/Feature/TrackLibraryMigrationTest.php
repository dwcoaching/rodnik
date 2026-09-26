<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->originalConnection = DB::getDefaultConnection();
    $this->originalSchema = Schema::getFacadeRoot();
    config(['database.connections.track_migration' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);
    DB::purge('track_migration');
    DB::setDefaultConnection('track_migration');
    Schema::swap(DB::connection()->getSchemaBuilder());

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
    });
    DB::table('users')->insert([['id' => 1], ['id' => 2]]);

    foreach ([
        '2026_09_08_153005_create_tracks_table.php',
        '2026_09_08_153007_create_maps_table.php',
        '2026_09_21_195501_add_is_starred_to_maps_table.php',
        '2026_09_22_190051_remove_description_from_maps_table.php',
        '2026_09_25_183730_create_track_uploads_table.php',
        '2026_09_26_173335_remove_token_from_maps_table.php',
    ] as $file) {
        (require database_path('migrations/'.$file))->up();
    }
    $this->consolidation = require database_path('migrations/2026_09_26_174003_consolidate_track_library_into_tracks.php');
});

afterEach(function (): void {
    Schema::swap($this->originalSchema);
    DB::setDefaultConnection($this->originalConnection);
    DB::purge('track_migration');
});

function legacyLibraryTrack(int $id, bool $public = true): array
{
    $geometry = json_encode(['type' => 'FeatureCollection', 'features' => [[
        'type' => 'Feature', 'properties' => ['name' => 'Original '.$id],
        'geometry' => ['type' => 'LineString', 'coordinates' => [[$id, 55], [$id + 0.1, 55.1]]],
    ]]], JSON_THROW_ON_ERROR);

    return ['id' => $id, 'hash' => hash('sha256', $geometry), 'track' => $geometry, 'user_id' => 1,
        'legacy_public' => $public, 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-02 12:00:00'];
}

function legacyLibraryMap(int $id, ?int $user, ?array $geometry, ?int $upload = null): array
{
    return ['id' => $id, 'slug' => 'map-'.$id, 'user_id' => $user, 'title' => 'Saved map '.$id,
        'state' => json_encode(['filters' => ['along' => $geometry !== null]], JSON_THROW_ON_ERROR),
        'track_hash' => $geometry['hash'] ?? null, 'track_upload_id' => $upload, 'track_deleted' => false,
        'version' => 7, 'views_count' => 123, 'is_starred' => true,
        'created_at' => '2026-09-03 12:00:00', 'updated_at' => '2026-09-04 12:00:00'];
}

test('track consolidation preserves public links ownership geometry map references and counters', function (): void {
    $shared = legacyLibraryTrack(7);
    $unused = legacyLibraryTrack(8);
    $revoked = legacyLibraryTrack(9, false);
    $survivingCopy = legacyLibraryTrack(10, false);
    DB::table('tracks')->insert([$shared, $unused, $revoked, $survivingCopy]);
    $uploads = [
        ['id' => 21, 'track_id' => 7, 'user_id' => 1, 'token' => 'Aa12345678', 'name' => 'Alice route'],
        ['id' => 22, 'track_id' => 7, 'user_id' => 2, 'token' => 'Bb12345678', 'name' => 'Bob route'],
        ['id' => 23, 'track_id' => 10, 'user_id' => 2, 'token' => 'Cc12345678', 'name' => 'Independent copy'],
    ];
    foreach ($uploads as &$upload) {
        $upload += ['summary' => '{"distance_km":12.34,"preview":[[37,55],[38,56]]}',
            'created_at' => '2026-09-05 12:00:00', 'updated_at' => '2026-09-06 12:00:00'];
    }
    unset($upload);
    DB::table('track_uploads')->insert($uploads);
    $maps = [
        legacyLibraryMap(31, 1, $shared, 21),
        legacyLibraryMap(32, 2, $shared, 21),
        legacyLibraryMap(33, 2, $shared, 22),
        legacyLibraryMap(34, 2, $shared),
        legacyLibraryMap(35, null, $shared),
        legacyLibraryMap(36, 1, null),
        legacyLibraryMap(37, 2, $survivingCopy, 23),
    ];
    DB::table('maps')->insert($maps);

    $this->consolidation->up();

    expect(Schema::hasTable('track_uploads'))->toBeFalse()
        ->and(DB::table('tracks')->count())->toBe(5);
    foreach ($uploads as $upload) {
        $row = (array) DB::table('tracks')->find($upload['id']);
        $geometry = $upload['track_id'] === 7 ? $shared : $survivingCopy;
        unset($upload['track_id']);
        expect($row)->toMatchArray([...$upload, 'hash' => $geometry['hash'], 'track' => $geometry['track']]);
    }
    $guest = DB::table('tracks')->where('hash', $shared['hash'])->whereNull('user_id')->sole();
    foreach ($maps as $map) {
        $expectedTrack = $map['track_upload_id'] ?? match ($map['id']) {
            34 => 22,
            35 => $guest->id,
            default => null,
        };
        unset($map['track_hash'], $map['track_upload_id'], $map['track_deleted']);
        expect((array) DB::table('maps')->find($map['id']))->toEqual([...$map, 'track_id' => $expectedTrack]);
    }
    expect(DB::table('tracks')->where('hash', $unused['hash'])->sole()->user_id)->toBe(1)
        ->and(DB::table('tracks')->where('hash', $revoked['hash'])->exists())->toBeFalse()
        ->and(DB::table('tracks')->where('hash', $survivingCopy['hash'])->where('user_id', 1)->exists())->toBeFalse();

    DB::table('tracks')->where('id', 21)->delete();
    expect(DB::table('maps')->where('id', 31)->value('track_id'))->toBeNull()
        ->and(DB::table('tracks')->where('id', 22)->exists())->toBeTrue();
});

test('empty track consolidation produces the final schema and refuses a lossy rollback', function (): void {
    $this->consolidation->up();

    expect(Schema::getColumnListing('tracks'))->toEqualCanonicalizing([
        'id', 'user_id', 'token', 'name', 'hash', 'track', 'summary', 'created_at', 'updated_at',
    ])->and(Schema::getColumnListing('maps'))->toEqualCanonicalizing([
        'id', 'slug', 'user_id', 'title', 'state', 'track_id', 'version', 'views_count', 'is_starred', 'created_at', 'updated_at',
    ])->and(Schema::hasTable('track_uploads'))->toBeFalse();

    expect(fn () => $this->consolidation->down())->toThrow(RuntimeException::class, 'Apply a forward migration instead.');
});

test('revoked legacy-only geometry is not republished by consolidation', function (): void {
    $revoked = legacyLibraryTrack(7, false);
    DB::table('tracks')->insert($revoked);
    DB::table('maps')->insert(legacyLibraryMap(31, 1, $revoked));

    $this->consolidation->up();

    expect(DB::table('tracks')->count())->toBe(0)
        ->and(DB::table('maps')->find(31)->track_id)->toBeNull()
        ->and(json_decode(DB::table('maps')->find(31)->state, true)['filters']['along'])->toBeFalse()
        ->and(DB::table('maps')->find(31)->views_count)->toBe(123)
        ->and(DB::table('maps')->find(31)->version)->toBe(7);
});
