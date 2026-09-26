<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table): void {
            $table->boolean('legacy_public')->default(true);
        });

        Schema::create('track_uploads', function (Blueprint $table): void {
            $table->id();
            $token = $table->char('token', 10)->unique();
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $token->charset('ascii')->collation('ascii_bin');
            }
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->json('summary');
            $table->timestamps();
            $table->unique(['user_id', 'track_id']);
            $table->index(['user_id', 'created_at', 'id']);
        });

        Schema::table('maps', function (Blueprint $table): void {
            $table->foreignId('track_upload_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('track_deleted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('track_upload_id');
            $table->dropColumn('track_deleted');
        });
        Schema::dropIfExists('track_uploads');
        Schema::table('tracks', function (Blueprint $table): void {
            $table->dropColumn('legacy_public');
        });
    }
};
