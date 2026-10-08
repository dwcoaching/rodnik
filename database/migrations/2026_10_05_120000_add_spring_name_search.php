<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Like the place names, so "zrodlo" finds "Źródło" (ł folds to l) while й stays distinct from и.
        $this->collate('utf8mb4_0900_ai_ci');

        Schema::table('springs', function (Blueprint $table): void {
            // Map search finds water sources by any word of their name ("святой" in "Святой источник").
            $table->fullText('name', 'springs_name_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('springs', function (Blueprint $table): void {
            $table->dropFullText('springs_name_fulltext');
        });

        $this->collate('utf8mb4_unicode_ci');
    }

    private function collate(string $collation): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `springs` MODIFY `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE {$collation} NULL");
    }
};
