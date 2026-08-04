<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('ground_up');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overpass_imports', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
