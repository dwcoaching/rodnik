<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maps', function (Blueprint $table): void {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table): void {
            $token = $table->char('token', 8)->nullable()->unique();

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $token->charset('ascii')->collation('ascii_bin');
            }
        });

        DB::table('maps')->orderBy('id')->eachById(function (object $map): void {
            do {
                $token = Str::random(8);
            } while (DB::table('maps')->where('token', $token)->exists());

            DB::table('maps')->where('id', $map->id)->update(['token' => $token]);
        });

        Schema::table('maps', function (Blueprint $table): void {
            $table->char('token', 8)->nullable(false)->change();
        });
    }
};
