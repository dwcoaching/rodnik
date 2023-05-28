<?php

use App\Models\OverpassBatch;
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
        Schema::create('overpass_batches', function (Blueprint $table) {
            $table->id();
            $table->string('imports_status')->nullable();
            $table->string('checks_status')->nullable();
            $table->string('fetch_status')->nullable();
            $table->double('coverage', 8, 5)->nullable();
            $table->string('parse_status')->nullable();
            $table->double('parsed_percentage', 8, 5)->nullable();
            $table->timestamps();
        });

        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->foreignId('overpass_batch_id');
        });

        Schema::table('overpass_checks', function (Blueprint $table) {
            $table->foreignId('overpass_batch_id');
        });

        $overpassBatch = new OverpassBatch();
        $overpassBatch->save();

        DB::table('overpass_imports')->update(['overpass_batch_id' => $overpassBatch->id]);
        DB::table('overpass_checks')->update(['overpass_batch_id' => $overpassBatch->id]);
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

        Schema::table('overpass_checks', function (Blueprint $table) {
            $table->dropColumn('overpass_batch_id');
        });

        Schema::dropIfExists('overpass_batches');
    }
};
