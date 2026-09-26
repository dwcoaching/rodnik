<?php

declare(strict_types=1);

use App\Support\TrackGeometry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tracks')->orderBy('id')->eachById(function (object $track): void {
            $geometry = json_decode($track->track);
            $owners = DB::table('maps')->where('track_hash', $track->hash)->distinct()->pluck('user_id')->all();
            $owners[] = $track->user_id;
            foreach (array_unique($owners, SORT_REGULAR) as $owner) {
                $existing = DB::table('track_uploads')->where('track_id', $track->id)->where('user_id', $owner)->first();
                $id = $existing?->id ?? DB::table('track_uploads')->insertGetId([
                    'token' => Str::random(10), 'track_id' => $track->id, 'user_id' => $owner,
                    'name' => TrackGeometry::name($geometry), 'summary' => json_encode(TrackGeometry::summary($geometry), JSON_THROW_ON_ERROR),
                    'created_at' => $track->created_at, 'updated_at' => $track->updated_at,
                ]);
                DB::table('maps')->where('track_hash', $track->hash)->where('user_id', $owner)->whereNull('track_upload_id')->update(['track_upload_id' => $id]);
            }
        });
    }

    public function down(): void {}
};
