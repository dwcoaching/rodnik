<?php

declare(strict_types=1);

use App\Support\TrackGeometry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_uploads', function (Blueprint $table): void {
            $table->char('hash', 64)->nullable();
            $table->json('track')->nullable();
        });

        foreach (DB::table('tracks')->lazyById(1) as $track) {
            $this->copyGeometry($track);
        }

        Schema::table('maps', function (Blueprint $table): void {
            $table->dropForeign(['track_hash']);
            $table->dropForeign(['track_upload_id']);
            $table->dropColumn(['track_hash', 'track_deleted']);
        });
        Schema::table('maps', function (Blueprint $table): void {
            $table->renameColumn('track_upload_id', 'track_id');
        });

        Schema::table('track_uploads', function (Blueprint $table): void {
            $table->dropForeign(['track_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'track_id']);
            $table->dropUnique(['token']);
            $table->dropIndex(['user_id', 'created_at', 'id']);
            $table->dropColumn('track_id');
        });
        Schema::drop('tracks');
        Schema::rename('track_uploads', 'tracks');

        Schema::table('tracks', function (Blueprint $table): void {
            $table->char('hash', 64)->nullable(false)->change();
            $table->json('track')->nullable(false)->change();
            $table->unique('token');
            $table->unique(['user_id', 'hash']);
            $table->index(['user_id', 'created_at', 'id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::table('maps', function (Blueprint $table): void {
            $table->foreign('track_id')->references('id')->on('tracks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Track consolidation cannot restore the removed global ownership and revocation metadata. Apply a forward migration instead.');
    }

    private function copyGeometry(object $track): void
    {
        $unlinkedMaps = DB::table('maps')->where('track_hash', $track->hash)->whereNull('track_upload_id');

        if ($track->legacy_public) {
            $owners = $unlinkedMaps->distinct()->pluck('user_id')->all();
            if (! DB::table('track_uploads')->where('track_id', $track->id)->exists()) {
                $owners[] = $track->user_id;
            }
            foreach (array_unique($owners, SORT_REGULAR) as $owner) {
                $id = DB::table('track_uploads')->where('track_id', $track->id)->where('user_id', $owner)->value('id');
                if ($id === null) {
                    $geometry = json_decode($track->track, false, 32, JSON_THROW_ON_ERROR);
                    do {
                        $token = Str::random(10);
                    } while (DB::table('track_uploads')->where('token', $token)->exists());
                    $id = DB::table('track_uploads')->insertGetId([
                        'token' => $token,
                        'track_id' => $track->id,
                        'user_id' => $owner,
                        'name' => TrackGeometry::name($geometry),
                        'summary' => json_encode(TrackGeometry::summary($geometry), JSON_THROW_ON_ERROR),
                        'created_at' => $track->created_at,
                        'updated_at' => $track->updated_at,
                    ]);
                }
                DB::table('maps')->where('track_hash', $track->hash)->whereNull('track_upload_id')
                    ->where('user_id', $owner)->update(['track_upload_id' => $id]);
            }
        } else {
            foreach ($unlinkedMaps->lazyById() as $map) {
                $state = json_decode($map->state, true, 32, JSON_THROW_ON_ERROR);
                $state['filters']['along'] = false;
                DB::table('maps')->where('id', $map->id)->update(['state' => json_encode($state, JSON_THROW_ON_ERROR)]);
            }
        }

        DB::table('track_uploads')->where('track_id', $track->id)->update(['hash' => $track->hash, 'track' => $track->track]);
    }
};
