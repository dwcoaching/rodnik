<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('springs', function (Blueprint $table) {
            $table->unsignedBigInteger('redirect_to_spring_id')->nullable()->after('hidden_at');
            $table->index('redirect_to_spring_id');
        });
    }

    public function down(): void
    {
        Schema::table('springs', function (Blueprint $table) {
            $table->dropIndex(['redirect_to_spring_id']);
            $table->dropColumn('redirect_to_spring_id');
        });
    }
};
