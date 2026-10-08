<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unlike utf8mb4_unicode_ci, this also folds ł, ø and đ, so place search finds Łódź for "lodz",
        // while й stays distinct from и and ё still equals е.
        $this->collate('utf8mb4_0900_ai_ci');
    }

    public function down(): void
    {
        $this->collate('utf8mb4_unicode_ci');
    }

    private function collate(string $collation): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['countries', 'regions', 'cities'] as $table) {
            // Both columns in one statement: the full-text index on cities needs them in the same collation,
            // and the table is rebuilt once. The name and full-text indexes are rebuilt with the columns.
            DB::statement("ALTER TABLE `{$table}`
                MODIFY `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE {$collation} NOT NULL,
                MODIFY `name_ru` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE {$collation} NULL");
        }
    }
};
