<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocoding_runs', function (Blueprint $table): void {
            $table->id();
            // A nullable unique key reserves one bulk run across CLI and web requests.
            $table->string('active_key')->nullable()->unique();
            $table->string('mode');
            $table->string('status');
            $table->unsignedBigInteger('max_spring_id')->default(0);
            $table->unsignedBigInteger('last_spring_id')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('matched')->default(0);
            $table->unsignedBigInteger('unmatched')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('reset_applied_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoding_runs');
    }
};
