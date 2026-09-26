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
        Schema::create('maps', function (Blueprint $table): void {
            $table->id();
            $token = $table->char('token', 8)->unique();
            $slug = $table->string('slug', 80)->unique();

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $token->charset('ascii')->collation('ascii_bin');
                $slug->charset('ascii')->collation('ascii_bin');
            }

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->json('state');
            $table->char('track_hash', 64)->nullable();
            $table->foreign('track_hash')->references('hash')->on('tracks')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'updated_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maps');
    }
};
