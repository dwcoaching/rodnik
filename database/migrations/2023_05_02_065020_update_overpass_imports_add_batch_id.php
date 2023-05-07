<?php

use App\Models\OverpassBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->foreignId('overpass_batch_id');
        });

        $overpassBatch = new OverpassBatch();
        $overpassBatch->save();

        DB::table('overpass_imports')
            ->update(
                [
                    'overpass_batch_id' => $overpassBatch->id,
                ]
            );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->dropColumn('overpass_batch_id');
        });
    }
};
